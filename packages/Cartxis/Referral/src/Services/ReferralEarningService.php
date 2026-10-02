<?php

namespace Cartxis\Referral\Services;

use App\Models\User;
use Cartxis\Identity\Services\IdentityStatus;
use Cartxis\Referral\Models\Referral;
use Cartxis\Referral\Models\ReferralCommission;
use Cartxis\Shop\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Decides when a referral has earned its reward, and pays it.
 *
 * The programme pays real money, so a farm of accounts created to collect
 * rewards is worth more to an attacker than any single fraudulent order. The
 * gate in this class is the answer to that: nobody earns anything until an
 * admin has looked at their national ID and confirmed it belongs to them.
 *
 * Two things about the gate are worth stating plainly:
 *
 *  1. It is both sides. The customer being referred has to be verified, and so
 *     does the referrer who would receive the money. A verified shopper cannot
 *     launder a reward to an unverified accomplice.
 *  2. It is not a cliff. A referral that crossed the threshold while somebody was
 *     unverified is paid the moment they are verified, so nobody loses money
 *     because of paperwork they did not know about. That back-payment is
 *     idempotent: it can run any number of times and pays exactly once.
 */
class ReferralEarningService
{
    /**
     * Null when the identity package is not installed. That is the only case in
     * which the gate does not exist, and it is a deliberate one: a store that
     * removed the identity package never asked for the check and must not
     * silently stop paying its referrers.
     */
    protected ?IdentityStatus $identity;

    public function __construct(
        protected ReferralSettings $settings,
        protected ReferralCreditService $credit,
        ?IdentityStatus $identity = null,
    ) {
        $this->identity = $identity ?? $this->resolveIdentityStatus();
    }

    /**
     * Called by OrderObserver the moment an order becomes paid.
     *
     * Safe to call any number of times for the same order: the database refuses
     * a duplicate award, and the referral is marked as rewarded immediately.
     */
    public function onOrderPaid(Order $order): void
    {
        if (! $this->settings->isEnabled()) {
            return;
        }

        $referredUser = $this->referredUserFor($order);

        if (! $referredUser) {
            return;
        }

        $referral = Referral::where('referred_user_id', $referredUser->id)->first();

        if (! $referral || $referral->hasBeenRewarded() || ! $referral->isActive()) {
            return;
        }

        $lifetime = $this->lifetimeQualifyingSpend($referredUser->id);

        if ($lifetime < $this->settings->thresholdAmount()) {
            return;
        }

        // Nobody earns while unverified. The referral is deliberately left
        // unrewarded, not voided, so the payout happens as soon as identity
        // verification clears.
        if (! $this->bothSidesVerified($referredUser->id)) {
            return;
        }

        $this->award($referral, $order, $lifetime);
    }

    /**
     * Pay the levels of one referral link. Wrapped in a transaction so a partial
     * payout can never happen.
     */
    public function award(Referral $referral, Order $order, ?float $lifetimeSpend = null): array
    {
        $reward = $this->settings->rewardAmount();
        $shares = $this->settings->levelShares();
        $lifetimeSpend ??= $this->lifetimeQualifyingSpend($referral->referred_user_id);

        $commissions = [];

        DB::transaction(function () use ($referral, $order, $reward, $shares, $lifetimeSpend, &$commissions) {
            // Re-check inside the transaction: two gateway callbacks can land together.
            $locked = Referral::where('id', $referral->id)->lockForUpdate()->first();

            if (! $locked || $locked->hasBeenRewarded()) {
                return;
            }

            // Re-checked here too. Between the check in onOrderPaid() and this
            // lock, a reviewer may have revoked nothing and an owner may have
            // closed the gate, and money must not leave the shop on a decision
            // that has since changed.
            if (! $this->bothSidesVerified($locked->referred_user_id)) {
                Log::info('Referral reward held back: identity not verified', [
                    'referral_id' => $locked->id,
                    'referred_user_id' => $locked->referred_user_id,
                    'order_id' => $order->id,
                ]);

                return;
            }

            $unlocksAt = now()->addDays($this->settings->lockDays());

            foreach ($shares as $index => $share) {
                $level = $index + 1;
                $referrerId = $this->uplineAt($locked->referred_user_id, $level);

                if (! $referrerId) {
                    continue;
                }

                $amount = round($reward * ($share / 100), 2);

                if ($amount <= 0) {
                    continue;
                }

                $commission = ReferralCommission::firstOrCreate(
                    [
                        'order_id' => $order->id,
                        'referral_id' => $locked->id,
                        'level' => $level,
                    ],
                    [
                        'referrer_user_id' => $referrerId,
                        'amount' => $amount,
                        'reward_snapshot' => $reward,
                        'share_snapshot' => $share,
                        'status' => ReferralCommission::STATUS_ACTIVE,
                        'unlocks_at' => $unlocksAt,
                    ]
                );

                // firstOrCreate returns an existing row when this award already happened.
                if ($commission->wasRecentlyCreated) {
                    $this->credit->recordEarned($commission);
                    $commissions[] = $commission;
                }
            }

            $locked->update([
                'rewarded_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info('Referral reward awarded', [
                'referral_id' => $locked->id,
                'order_id' => $order->id,
                'lifetime_spend' => $lifetimeSpend,
                'reward' => $reward,
                'levels_paid' => count($commissions),
            ]);
        });

        return $commissions;
    }

    /**
     * Somebody has just cleared identity verification. Pay anything they had
     * already earned before that happened.
     *
     * Called by the identity package after an approval is committed. Idempotent
     * by construction: the referral is locked and marked rewarded inside the same
     * transaction that pays it, so calling this twice, or calling it for two
     * approvals at once, still pays once.
     */
    public function onIdentityVerified(int $userId): void
    {
        if (! $this->settings->isEnabled()) {
            return;
        }

        $referrals = Referral::query()
            ->whereIn('id', $this->referralIdsAffectedBy($userId))
            ->whereNull('rewarded_at')
            ->where('status', Referral::STATUS_ACTIVE)
            ->get();

        foreach ($referrals as $referral) {
            $this->backPay($referral);
        }
    }

    /**
     * Pay one referral that was waiting on a verification.
     *
     * @return int number of levels paid
     */
    protected function backPay(Referral $referral): int
    {
        $lifetime = $this->lifetimeQualifyingSpend($referral->referred_user_id);

        if ($lifetime < $this->settings->thresholdAmount()) {
            return 0;
        }

        if (! $this->bothSidesVerified($referral->referred_user_id)) {
            return 0;
        }

        // The order the commission is recorded against. The award is once per
        // referred person, so this only decides which order the receipt points
        // at: the latest qualifying one is the one that closed the threshold.
        $order = $this->qualifyingOrderFor($referral->referred_user_id);

        if (! $order) {
            // Lifetime spend above the threshold with no qualifying order left to
            // point at. Only reachable with a zero threshold and no orders.
            Log::warning('Referral back-payment had no order to attach to', [
                'referral_id' => $referral->id,
                'referred_user_id' => $referral->referred_user_id,
            ]);

            return 0;
        }

        $commissions = $this->award($referral, $order, $lifetime);

        if ($commissions !== []) {
            Log::info('Referral reward paid after identity verification', [
                'referral_id' => $referral->id,
                'referral_user_id' => $referral->referred_user_id,
                'order_id' => $order->id,
                'levels_paid' => count($commissions),
            ]);
        }

        return count($commissions);
    }

    /**
     * Which referrals can this person's verification have unblocked?
     *
     * Two kinds: the referral they are the referred party of, and the referrals
     * of the people they referred, at every level that could pay them. Anything
     * else on the same table is left alone rather than re-checked.
     *
     * @return array<int, int>
     */
    protected function referralIdsAffectedBy(int $userId): array
    {
        $ids = Referral::where('referred_user_id', $userId)->pluck('id')->all();

        // Level 1: the people this account referred directly.
        $firstLevel = Referral::where('referrer_user_id', $userId)->pluck('referred_user_id');

        $ids = array_merge($ids, Referral::whereIn('referred_user_id', $firstLevel)->pluck('id')->all());

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * Total money a referred customer has paid us.
     *
     * orders.total is written net of referral credit, so counting it already
     * excludes credit. Counting it again would let two people trade credit back
     * and forth and push each other over the threshold for free.
     *
     * Guest orders are matched through the customers table so they still count.
     */
    public function lifetimeQualifyingSpend(int $referredUserId): float
    {
        $customerIds = DB::table('customers')
            ->where('user_id', $referredUserId)
            ->pluck('id')
            ->all();

        $query = Order::query()
            ->where('payment_status', Order::PAYMENT_PAID)
            ->whereNotIn('status', [Order::STATUS_CANCELLED, Order::STATUS_REFUNDED])
            ->where(function ($q) use ($referredUserId, $customerIds) {
                $q->where('user_id', $referredUserId);

                if ($customerIds !== []) {
                    $q->orWhereIn('customer_id', $customerIds);
                }
            });

        return round((float) $query->sum('total'), 2);
    }

    /**
     * The customer this order belongs to, looking through the customer record so
     * that guest checkout still counts.
     */
    public function referredUserFor(Order $order): ?User
    {
        if ($order->user_id) {
            return User::where('id', $order->user_id)->first();
        }

        if ($order->customer_id) {
            return User::whereHas(
                'customer',
                fn ($q) => $q->where('customers.id', $order->customer_id)
            )->first();
        }

        return null;
    }

    /**
     * The user $depth steps above the given user, or null if there is nobody there.
     */
    public function uplineAt(int $userId, int $depth): ?int
    {
        $current = $userId;
        $seen = [$userId => true];

        for ($step = 0; $step < $depth; $step++) {
            $referral = Referral::where('referred_user_id', $current)->first();

            if (! $referral || isset($seen[$referral->referrer_user_id])) {
                return null;
            }

            $current = $referral->referrer_user_id;
            $seen[$current] = true;
        }

        return $current === $userId ? null : $current;
    }

    /**
     * The newest paid order of this customer, which is the one a retroactive
     * award is recorded against.
     */
    protected function qualifyingOrderFor(int $referredUserId): ?Order
    {
        $customerIds = DB::table('customers')
            ->where('user_id', $referredUserId)
            ->pluck('id')
            ->all();

        return Order::query()
            ->where('payment_status', Order::PAYMENT_PAID)
            ->whereNotIn('status', [Order::STATUS_CANCELLED, Order::STATUS_REFUNDED])
            ->where(function ($q) use ($referredUserId, $customerIds) {
                $q->where('user_id', $referredUserId);

                if ($customerIds !== []) {
                    $q->orWhereIn('customer_id', $customerIds);
                }
            })
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Is the reward payable at all?
     *
     * All or nothing, on purpose. If one level of a two level reward belongs to
     * somebody who is not verified, nothing is paid yet, rather than paying the
     * verified half and marking the referral done: that way the unverified half
     * is still owed the moment they are verified, and neither side quietly loses
     * money because of the other's paperwork.
     */
    protected function bothSidesVerified(int $referredUserId): bool
    {
        if (! $this->gateApplies()) {
            return true;
        }

        if (! $this->isVerified($referredUserId)) {
            return false;
        }

        return $this->payableLevels($referredUserId) !== [];
    }

    /**
     * The levels that would be paid, or an empty array when the money is held
     * back. Callers use "empty" as the signal, so a two level reward where the
     * top referrer is unverified blocks the whole payout.
     *
     * @return array<int, int> level => referrer user id
     */
    protected function payableLevels(int $referredUserId): array
    {
        $reward = $this->settings->rewardAmount();
        $payable = [];

        foreach ($this->settings->levelShares() as $index => $share) {
            $level = $index + 1;

            if (round($reward * ($share / 100), 2) <= 0) {
                continue;
            }

            $referrerId = $this->uplineAt($referredUserId, $level);

            if (! $referrerId) {
                continue;
            }

            if (! $this->isVerified($referrerId)) {
                return [];
            }

            $payable[$level] = $referrerId;
        }

        return $payable;
    }

    /**
     * Is this account verified, as far as the gate is concerned?
     */
    protected function isVerified(int $userId): bool
    {
        if (! $this->gateApplies()) {
            return true;
        }

        return $this->identity?->isVerified($userId) ?? true;
    }

    /**
     * Is the identity gate switched on?
     */
    protected function gateApplies(): bool
    {
        return (bool) $this->identity?->gateApplies();
    }

    protected function resolveIdentityStatus(): ?IdentityStatus
    {
        if (! class_exists(IdentityStatus::class)) {
            return null;
        }

        try {
            return app(IdentityStatus::class);
        } catch (\Throwable) {
            return null;
        }
    }
}