<?php

namespace Cartxis\Referral\Services;

use App\Models\User;
use Cartxis\Referral\Models\Referral;
use Cartxis\Referral\Models\ReferralCommission;
use Cartxis\Shop\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Decides when a referral has earned its reward, and pays it.
 */
class ReferralEarningService
{
    public function __construct(
        protected ReferralSettings $settings,
        protected ReferralCreditService $credit,
    ) {}

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
}
