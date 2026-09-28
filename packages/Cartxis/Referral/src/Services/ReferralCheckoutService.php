<?php

namespace Cartxis\Referral\Services;

use App\Models\User;
use Cartxis\Shop\Models\Order;

/**
 * The checkout side of referral credit: how much may be used on this order, and
 * taking it.
 */
class ReferralCheckoutService
{
    public function __construct(
        protected ReferralSettings $settings,
        protected ReferralCreditService $credit,
    ) {}

    /**
     * How much of an order of this size may be paid with credit.
     *
     * Credit is capped at the settings' percentage of the order, so a customer
     * can never wipe out shipping and tax entirely.
     */
    public function applicableAmount(?User $user, float $orderTotal): float
    {
        if (! $this->settings->isEnabled() || ! $user || $orderTotal <= 0) {
            return 0.0;
        }

        $available = $this->credit->availableBalance($user->id);

        if ($available <= 0) {
            return 0.0;
        }

        $cap = $orderTotal * ($this->settings->creditMaxPercentOfOrder() / 100);

        return round(min($available, $cap, $orderTotal), 2);
    }

    /**
     * The balance a signed-in customer can see offered at checkout.
     */
    public function availableFor(?User $user): float
    {
        if (! $this->settings->isEnabled() || ! $user) {
            return 0.0;
        }

        return round($this->credit->availableBalance($user->id), 2);
    }

    /**
     * Take the credit for an order that has just been created but not yet paid.
     *
     * $goodsTotal is the part of the order the credit may cover, i.e. everything
     * except shipping. Passing $order->total here would let credit swallow the
     * courier's fee, which is a real cost to the shop and never recoverable.
     *
     * Reserved now, not when the payment lands, so the same credit cannot be
     * spent twice on two orders placed at the same time. The observer gives it
     * back if the order is cancelled or never paid.
     */
    public function reserve(?User $user, float $requested, Order $order, ?float $goodsTotal = null): float
    {
        if (! $this->settings->isEnabled() || ! $user || $requested <= 0) {
            return 0.0;
        }

        $goodsTotal ??= max(0, (float) $order->subtotal + (float) $order->tax - (float) $order->discount);
        $goodsTotal = max(0, $goodsTotal);

        $allowed = $this->applicableAmount($user, $goodsTotal);

        if ($allowed <= 0) {
            return 0.0;
        }

        // Never more than what the order still owes after credit.
        $allowed = min($allowed, max(0, (float) $order->total));

        $take = $this->credit->spend($user->id, min($requested, $allowed), $order->id);

        if ($take > 0) {
            // The order total has to come down by exactly what was taken, or the
            // customer loses credit and still pays full price.
            $order->forceFill([
                'credit_applied' => $take,
                'total' => max(0, round((float) $order->total - $take, 2)),
            ])->save();
        }

        return $take;
    }
}
