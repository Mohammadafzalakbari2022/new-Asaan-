<?php

namespace Cartxis\Referral\Observers;

use Cartxis\Referral\Services\ReferralCreditService;
use Cartxis\Referral\Services\ReferralEarningService;
use Cartxis\Shop\Models\Order;
use Illuminate\Support\Facades\Log;

/**
 * The single point where the referral programme reacts to an order.
 *
 * Fifteen separate places in the codebase write payment_status = 'paid' —
 * storefront checkout, four API checkout methods, Stripe, Razorpay, PayPal,
 * PayU, PhonePe, and the admin's "mark invoice paid" button. Only one of them
 * goes through a shared service, so awarding from any single one would miss the
 * rest. Watching the model catches all of them, including any added later.
 */
class OrderObserver
{
    public function __construct(
        protected ReferralEarningService $earning,
        protected ReferralCreditService $credit,
    ) {}

    /**
     * Some paths create the order already marked paid, so the reward must be
     * considered on insert as well as on update. Without this, an order created
     * paid never triggers anything, because 'updated' never fires for it.
     */
    public function created(Order $order): void
    {
        try {
            if ($order->payment_status === Order::PAYMENT_PAID) {
                $this->earning->onOrderPaid($order);
            }
        } catch (\Throwable $e) {
            Log::error('Referral handling failed on order create', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function updated(Order $order): void
    {
        try {
            if ($order->wasChanged('payment_status')) {
                $this->handlePaymentStatusChange($order);
            }

            if ($order->wasChanged('status') && $order->status === Order::STATUS_CANCELLED) {
                $this->handleCancellation($order);
            }
        } catch (\Throwable $e) {
            // A referral problem must never take a paid order down with it.
            Log::error('Referral order handling failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function handlePaymentStatusChange(Order $order): void
    {
        $status = $order->payment_status;

        if ($status === Order::PAYMENT_PAID) {
            $this->earning->onOrderPaid($order);

            return;
        }

        if ($status === Order::PAYMENT_REFUNDED) {
            $count = $this->credit->reverseCommissionsForOrder(
                $order,
                'Order '.$order->order_number.' was refunded.'
            );

            if ($count > 0) {
                Log::info('Referral rewards reversed on refund', [
                    'order_id' => $order->id,
                    'commissions' => $count,
                ]);
            }
        }
    }

    protected function handleCancellation(Order $order): void
    {
        $released = $this->credit->releaseForOrder($order->id);

        if ($released > 0) {
            Log::info('Referral credit returned on cancellation', [
                'order_id' => $order->id,
                'released' => $released,
            ]);
        }
    }
}
