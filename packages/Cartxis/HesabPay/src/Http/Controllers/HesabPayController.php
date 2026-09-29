<?php

namespace Cartxis\HesabPay\Http\Controllers;

use App\Http\Controllers\Controller;
use Cartxis\Core\Models\EmailTemplate;
use Cartxis\Core\Services\PaymentGatewayManager;
use Cartxis\HesabPay\Services\HesabPayGateway;
use Cartxis\Sales\Services\InvoiceService;
use Cartxis\Sales\Services\TransactionService;
use Cartxis\Shop\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class HesabPayController extends Controller
{
    protected PaymentGatewayManager $gatewayManager;
    protected InvoiceService $invoiceService;
    protected TransactionService $transactionService;

    public function __construct(
        PaymentGatewayManager $gatewayManager,
        InvoiceService $invoiceService,
        TransactionService $transactionService
    ) {
        $this->gatewayManager = $gatewayManager;
        $this->invoiceService = $invoiceService;
        $this->transactionService = $transactionService;
    }

    protected function gateway(): ?HesabPayGateway
    {
        $gateway = $this->gatewayManager->get('hesabpay');

        return $gateway instanceof HesabPayGateway ? $gateway : null;
    }

    /**
     * The customer returns here after paying on HesabPay's page.
     *
     * This proves nothing about the payment, so the order is deliberately left
     * pending and the page says confirmation is in progress. Only a verified
     * webhook marks it paid.
     */
    public function success(Request $request, $orderId): RedirectResponse
    {
        $order = Order::findOrFail($orderId);

        // Keep the guest success page authorised for this order.
        Session::put('checkout.last_order_id', $order->id);

        // The customer has left the store, so the basket has served its purpose.
        // The basket cannot be cleared from the webhook because that request
        // carries none of the shopper's session.
        Session::forget('cart');

        $gateway = $this->gateway();

        if ($gateway && $gateway->verifyPayment($order)) {
            return redirect()
                ->route('shop.checkout.success', ['order' => $order->id])
                ->with('success', 'Payment confirmed. Thank you for your order!');
        }

        return redirect()
            ->route('shop.checkout.success', ['order' => $order->id])
            ->with('success', 'We are confirming your payment. This page updates automatically once HesabPay confirms it.');
    }

    /**
     * The customer returns here after failing or cancelling at HesabPay.
     *
     * The basket is left alone so they can retry without rebuilding it.
     */
    public function failure(Request $request, $orderId): RedirectResponse
    {
        $order = Order::findOrFail($orderId);

        Session::put('checkout.last_order_id', $order->id);

        // Never downgrade an order that a webhook already confirmed as paid.
        if ($order->payment_status !== Order::PAYMENT_PAID) {
            $order->update(['payment_status' => Order::PAYMENT_FAILED]);
        }

        return redirect()
            ->route('shop.checkout.success', ['order' => $order->id])
            ->with('error', 'The payment was not completed. Your order is saved and you can try paying again.');
    }

    /**
     * HesabPay server-to-server payment notification.
     */
    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->json()->all();

        $gateway = $this->gateway();

        if (!$gateway) {
            Log::error('HesabPayController: Gateway not registered');

            return response()->json(['error' => 'Gateway not found'], 500);
        }

        $result = $gateway->handleWebhook($payload);

        if (!($result['handled'] ?? false)) {
            // Unverified or unmatched. Tell HesabPay so it does not treat a
            // retry as delivered, but never act on the payload.
            return response()->json(
                ['error' => $result['message'] ?? 'Webhook rejected'],
                400
            );
        }

        $order = $result['order'] ?? null;

        if (($result['outcome'] ?? null) === 'payment_success' && $order) {
            $this->settleOrder($order, $payload);
        }

        // Anything verified and matched is a successful delivery, including a
        // duplicate. Answering 200 stops HesabPay retrying work already done.
        return response()->json(['status' => 'success']);
    }

    /**
     * Book the confirmed payment against the order.
     */
    protected function settleOrder(Order $order, array $payload): void
    {
        $transactionId = $payload['transaction_id'] ?? null;

        $invoice = $this->invoiceService->createFromOrderIfMissing($order);

        $this->transactionService->createPaymentIfMissing($order, [
            'payment_method' => 'hesabpay',
            'gateway' => 'hesabpay',
            'gateway_transaction_id' => $transactionId,
            'amount' => (float) $order->total,
            'status' => 'completed',
            'notes' => 'Payment completed via HesabPay',
            'invoice_id' => $invoice?->id,
        ]);

        try {
            $template = EmailTemplate::findByCode('order_placed');

            if ($template) {
                $shippingAddress = $order->shippingAddress;
                $customerName = $shippingAddress
                    ? trim($shippingAddress->first_name . ' ' . $shippingAddress->last_name)
                    : ($order->customer_name ?? 'Customer');

                $template->send($order->customer_email, [
                    'customer_name' => $customerName,
                    'order_number' => $order->order_number,
                    'order_date' => $order->created_at->format('F j, Y'),
                    'order_total' => number_format((float) $order->total, 2) . ' AFN',
                    'store_name' => config('app.name', 'Cartxis'),
                    'store_url' => url('/'),
                ]);
            }
        } catch (\Exception $e) {
            // A failed confirmation email must not undo a real payment.
            Log::error('Order confirmation email failed after HesabPay payment', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
