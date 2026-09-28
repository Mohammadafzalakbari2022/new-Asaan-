<?php

namespace Cartxis\Referral\Services;

use App\Models\User;
use Cartxis\Referral\Models\ReferralCommission;
use Cartxis\Referral\Models\ReferralLedgerEntry;
use Cartxis\Shop\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * The only place a referral credit balance is allowed to change.
 *
 * Balances are computed from the ledger rather than stored on the user, because a
 * stored balance drifts out of step with reality and nobody notices until money
 * is owed to a real person.
 */
class ReferralCreditService
{
    public function __construct(protected ReferralSettings $settings) {}

    /**
     * Credit the customer can spend right now.
     */
    public function availableBalance(int $userId): float
    {
        return (float) ReferralLedgerEntry::where('user_id', $userId)
            ->where('available_from', '<=', now())
            ->sum('amount');
    }

    /**
     * Credit earned but still inside the lock period.
     */
    public function lockedBalance(int $userId): float
    {
        return (float) ReferralLedgerEntry::where('user_id', $userId)
            ->where('type', ReferralLedgerEntry::TYPE_EARNED)
            ->where('available_from', '>', now())
            ->sum('amount');
    }

    /**
     * Every afghani of referral credit this customer has ever been given, minus
     * what has been reversed. Includes locked and already-spent credit.
     */
    public function lifetimeEarned(int $userId): float
    {
        $earned = (float) ReferralLedgerEntry::where('user_id', $userId)
            ->whereIn('type', [ReferralLedgerEntry::TYPE_EARNED, ReferralLedgerEntry::TYPE_ADMIN_CREDIT])
            ->sum('amount');

        $reversed = (float) ReferralLedgerEntry::where('user_id', $userId)
            ->where('type', ReferralLedgerEntry::TYPE_REVERSED)
            ->sum('amount');

        $debits = (float) ReferralLedgerEntry::where('user_id', $userId)
            ->where('type', ReferralLedgerEntry::TYPE_ADMIN_DEBIT)
            ->sum('amount');

        return round($earned + $reversed + $debits, 2);
    }

    /**
     * Credit already used at checkout.
     */
    public function totalSpent(int $userId): float
    {
        return abs((float) ReferralLedgerEntry::where('user_id', $userId)
            ->where('type', ReferralLedgerEntry::TYPE_SPENT)
            ->sum('amount'));
    }

    /**
     * Total account value: locked + available. This is what the ledger's
     * balance_after column snapshots after every write.
     */
    public function totalBalance(int $userId): float
    {
        return (float) ReferralLedgerEntry::where('user_id', $userId)->sum('amount');
    }

    /**
     * Used by the account-deletion guard.
     */
    public function hasAnyBalance(int $userId): bool
    {
        return $this->totalBalance($userId) > 0;
    }

    public function balancesFor(User $user): array
    {
        return [
            'available' => round($this->availableBalance($user->id), 2),
            'locked' => round($this->lockedBalance($user->id), 2),
            'earned' => round($this->lifetimeEarned($user->id), 2),
            'spent' => round($this->totalSpent($user->id), 2),
        ];
    }

    /**
     * Book a freshly awarded commission into the ledger.
     */
    public function recordEarned(ReferralCommission $commission): ReferralLedgerEntry
    {
        return $this->write(
            userId: $commission->referrer_user_id,
            type: ReferralLedgerEntry::TYPE_EARNED,
            amount: (float) $commission->amount,
            availableFrom: $commission->unlocks_at ?? now(),
            commissionId: $commission->id,
            orderId: $commission->order_id,
        );
    }

    /**
     * Spend credit against an order.
     *
     * Returns the amount actually taken, which is 0 when there is nothing
     * available. Never returns more than the available balance, and never leaves
     * a negative balance, even with two orders racing for the same credit.
     */
    public function spend(int $userId, float $amount, ?int $orderId = null): float
    {
        if ($amount <= 0) {
            return 0.0;
        }

        return DB::transaction(function () use ($userId, $amount, $orderId) {
            // Serialise competing spends for this one customer.
            ReferralLedgerEntry::where('user_id', $userId)->lockForUpdate()->get();

            $available = $this->availableBalance($userId);
            $take = round(min($amount, max($available, 0.0)), 2);

            if ($take <= 0) {
                return 0.0;
            }

            $this->write(
                userId: $userId,
                type: ReferralLedgerEntry::TYPE_SPENT,
                amount: -$take,
                availableFrom: now(),
                orderId: $orderId,
            );

            return $take;
        });
    }

    /**
     * Hand-credit a customer by hand. Requires a written reason.
     */
    public function adminCredit(int $userId, float $amount, int $adminId, string $reason): ReferralLedgerEntry
    {
        return $this->write(
            userId: $userId,
            type: ReferralLedgerEntry::TYPE_ADMIN_CREDIT,
            amount: round($amount, 2),
            availableFrom: now(),
            adminId: $adminId,
            reason: $reason,
        );
    }

    /**
     * Take credit back by hand. Requires a written reason.
     */
    public function adminDebit(int $userId, float $amount, int $adminId, string $reason): ReferralLedgerEntry
    {
        return $this->write(
            userId: $userId,
            type: ReferralLedgerEntry::TYPE_ADMIN_DEBIT,
            amount: round(-$amount, 2),
            availableFrom: now(),
            adminId: $adminId,
            reason: $reason,
        );
    }

    /**
     * Reverse a single commission, taking its credit back.
     */
    public function reverseCommission(ReferralCommission $commission, string $reason, ?int $adminId = null): void
    {
        if ($commission->isReversed()) {
            return;
        }

        DB::transaction(function () use ($commission, $reason, $adminId) {
            $commission->update([
                'status' => ReferralCommission::STATUS_REVERSED,
                'reversed_at' => now(),
                'reversal_reason' => $reason,
            ]);

            $this->write(
                userId: $commission->referrer_user_id,
                type: ReferralLedgerEntry::TYPE_REVERSED,
                amount: -round((float) $commission->amount, 2),
                availableFrom: now(),
                commissionId: $commission->id,
                orderId: $commission->order_id,
                adminId: $adminId,
                reason: $reason,
            );
        });
    }

    /**
     * Reverse every commission that a given order triggered.
     */
    public function reverseCommissionsForOrder(Order $order, string $reason, ?int $adminId = null): int
    {
        $commissions = ReferralCommission::where('order_id', $order->id)
            ->where('status', ReferralCommission::STATUS_ACTIVE)
            ->get();

        foreach ($commissions as $commission) {
            $this->reverseCommission($commission, $reason, $adminId);
        }

        return $commissions->count();
    }

    /**
     * Give back the credit that was reserved on an order that never got paid.
     */
    public function releaseForOrder(int $orderId): float
    {
        return DB::transaction(function () use ($orderId) {
            $entries = ReferralLedgerEntry::where('order_id', $orderId)
                ->where('type', ReferralLedgerEntry::TYPE_SPENT)
                ->lockForUpdate()
                ->get();

            $released = 0.0;

            foreach ($entries as $entry) {
                $amount = abs((float) $entry->amount);

                $this->write(
                    userId: $entry->user_id,
                    type: ReferralLedgerEntry::TYPE_REVERSED,
                    amount: $amount,
                    availableFrom: now(),
                    orderId: $orderId,
                    reason: 'Order was cancelled or not paid, credit returned.',
                );

                $released += $amount;
            }

            return round($released, 2);
        });
    }

    /**
     * The single write path. Everything above funnels here so the ledger stays complete.
     */
    protected function write(
        int $userId,
        string $type,
        float $amount,
        $availableFrom = null,
        ?int $commissionId = null,
        ?int $orderId = null,
        ?int $adminId = null,
        ?string $reason = null,
    ): ReferralLedgerEntry {
        $amount = round($amount, 2);

        return DB::transaction(function () use (
            $userId,
            $type,
            $amount,
            $availableFrom,
            $commissionId,
            $orderId,
            $adminId,
            $reason
        ) {
            $entry = ReferralLedgerEntry::create([
                'user_id' => $userId,
                'type' => $type,
                'amount' => $amount,
                'available_from' => $availableFrom ?? now(),
                'commission_id' => $commissionId,
                'order_id' => $orderId,
                'admin_id' => $adminId,
                'reason' => $reason,
            ]);

            $entry->update([
                'balance_after' => (float) ReferralLedgerEntry::where('user_id', $userId)->sum('amount'),
            ]);

            return $entry;
        });
    }
}
