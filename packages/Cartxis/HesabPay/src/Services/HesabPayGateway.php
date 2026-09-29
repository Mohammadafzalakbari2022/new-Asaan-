<?php

namespace Cartxis\HesabPay\Services;

use Cartxis\Core\Contracts\PaymentGatewayInterface;
use Cartxis\Core\Models\PaymentMethod;
use Cartxis\Shop\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HesabPay Payment Gateway Implementation
 *
 * HesabPay is a hosted-checkout gateway: we create a payment session, the
 * customer pays on HesabPay's own page, and a signed webhook tells us the
 * result. The browser redirect is only for the customer's benefit, so nothing
 * here trusts it.
 *
 * Docs: https://docs.hesab.com/
 */
class HesabPayGateway implements PaymentGatewayInterface
{
    protected ?PaymentMethod $paymentMethod = null;

    /**
     * Our own reference handed to HesabPay as `user_id` and echoed back in the
     * success webhook. Session key used to cache the resolved method.
     */
    protected const CACHE_KEY = 'hesabpay.payment_method';

    /**
     * Fetch the active payment method row holding our configuration.
     */
    protected function getPaymentMethod(): ?PaymentMethod
    {
        if (!$this->paymentMethod) {
            $this->paymentMethod = PaymentMethod::where('code', 'hesabpay')
                ->where('is_active', true)
                ->first();
        }

        return $this->paymentMethod;
    }

    /**
     * Current environment: "sandbox" or "production".
     */
    public function getMode(): string
    {
        $mode = (string) ($this->getPaymentMethod()?->getConfigValue('mode')
            ?? config('hesabpay.default_mode', 'sandbox'));

        return $mode === 'production' ? 'production' : 'sandbox';
    }

    /**
     * Base URL for the active environment.
     */
    public function getBaseUrl(): string
    {
        $urls = config('hesabpay.base_url', []);

        return (string) ($urls[$this->getMode()] ?? $urls['sandbox'] ?? 'https://api-sandbox.hesab.com');
    }

    /**
     * Read a configuration value.
     */
    protected function getConfig(string $key, mixed $default = null): mixed
    {
        $method = $this->getPaymentMethod();

        if (!$method) {
            return $default;
        }

        return $method->getConfigValue($key, $default);
    }

    /**
     * API key for the active environment.
     *
     * Admin-saved keys win. The env var is a fallback so a deployment can keep
     * the secret out of the database entirely.
     */
    public function getApiKey(): ?string
    {
        $mode = $this->getMode();

        $key = $this->getConfig($mode === 'sandbox' ? 'test_api_key' : 'api_key');

        if (empty($key)) {
            $key = config('hesabpay.env_api_key');
        }

        return is_string($key) && $key !== '' ? $key : null;
    }

    /**
     * HTTP client pointed at the active environment with the API key attached.
     *
     * Deliberately no retry: Laravel's retry helper turns any non-2xx into a
     * thrown exception, which would hide the gateway's own error message, and
     * repeating a session creation risks opening two checkout sessions for one
     * order. A network blip fails closed and the shopper simply tries again.
     */
    protected function client()
    {
        $apiKey = $this->getApiKey();

        if (!$apiKey) {
            throw new \Exception('HesabPay API key is not configured');
        }

        return Http::baseUrl($this->getBaseUrl())
            ->withHeaders([
                'Authorization' => 'API-KEY ' . $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->timeout((int) config('hesabpay.timeout', 30));
    }

    /**
     * Read the HesabPay block out of the order's payment_data JSON.
     */
    public static function paymentData(Order $order): array
    {
        $data = $order->payment_data;

        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        if (! is_array($data)) {
            return [];
        }

        $block = $data['hesabpay'] ?? [];

        return is_array($block) ? $block : [];
    }

    /**
     * Merge values into the order's payment_data JSON, leaving any other
     * gateway's block untouched.
     */
    protected static function writePaymentData(Order $order, array $values): void
    {
        $data = $order->payment_data;

        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        if (! is_array($data)) {
            $data = [];
        }

        $data['hesabpay'] = array_merge(self::paymentData($order), $values);

        $order->payment_data = $data;
        $order->save();
    }

    /**
     * {@inheritdoc}
     */
    public function getCode(): string
    {
        return 'hesabpay';
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'HesabPay';
    }

    /**
     * {@inheritdoc}
     */
    public function supports(string $paymentMethod): bool
    {
        return $paymentMethod === 'hesabpay';
    }

    /**
     * {@inheritdoc}
     */
    public function processPayment(Order $order, array $data = [])
    {
        // Checked before the try block on purpose: the client builder throws when
        // the key is missing, and the catch-all below would report a missing
        // key as a network failure, which sends the shopper off retrying a
        // payment that can never work.
        if (! $this->isConfigured()) {
            Log::error('HesabPayGateway: Cannot start payment, no API key for the active mode', [
                'order_id' => $order->id,
                'mode' => $this->getMode(),
            ]);

            return [
                'success' => false,
                'message' => 'HesabPay is not set up for this store yet. Please contact us to pay another way.',
            ];
        }

        // A fully discounted or fully credited order has nothing to charge. The
        // API requires a non-empty items array, and there is no correct amount
        // to send, so this has to be settled in the admin rather than online.
        if (round((float) $order->total, 2) <= 0) {
            Log::warning('HesabPayGateway: Refusing to open a session for a zero total order', [
                'order_id' => $order->id,
                'total' => (float) $order->total,
            ]);

            return [
                'success' => false,
                'message' => 'This order has a total of zero, so there is nothing to pay online.',
            ];
        }

        try {
            $response = $this->client()->post('/api/v1/payment/create-session', [
                'email' => $order->customer_email ?: null,
                // HesabPay returns this untouched, so it is how the webhook
                // finds the order. order_number is unique and human readable.
                'user_id' => $order->order_number,
                'items' => $this->buildItems($order),
                'redirect_success_url' => route('hesabpay.return.success', ['order' => $order->id]),
                'redirect_failure_url' => route('hesabpay.return.failure', ['order' => $order->id]),
            ]);

            $body = $response->json();

            if ($response->failed() || empty($body['url']) || ($body['success'] ?? false) !== true) {
                Log::error('HesabPayGateway: Session creation rejected', [
                    'order_id' => $order->id,
                    'status' => $response->status(),
                    'message' => $body['message'] ?? null,
                    'detail' => $body['detail'] ?? null,
                ]);

                return [
                    'success' => false,
                    'message' => $body['message']
                        ?? $body['detail']
                        ?? 'HesabPay could not start the payment. Please try again.',
                ];
            }

            self::writePaymentData($order, [
                'order_reference' => $order->order_number,
                'checkout_url' => $body['url'],
                'amount' => (float) $order->total,
                'mode' => $this->getMode(),
                'transaction_id' => null,
            ]);

            Log::info('HesabPayGateway: Session created', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'mode' => $this->getMode(),
            ]);

            return new RedirectResponse($body['url']);

        } catch (\Exception $e) {
            Log::error('HesabPayGateway: Session creation failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Could not reach HesabPay. Please try again.',
            ];
        }
    }

    /**
     * A single line representing the whole order, used when the itemised lines
     * cannot express the exact total.
     */
    protected function summaryItem(Order $order): array
    {
        return [
            'id' => mb_substr((string) $order->order_number, 0, 50),
            'name' => mb_substr("Order #{$order->order_number}", 0, 500),
            'price' => round((float) $order->total, 2),
        ];
    }

    /**
     * Line items for the checkout page.
     *
     * HesabPay has no quantity field and, per its API reference, "the gateway
     * calculates the payment amount from the submitted item prices". So the
     * prices sent here must add up to exactly what the customer is charged,
     * which is orders.total -- not the product subtotal. Any difference from
     * tax, shipping, coupons or referral credit has to be carried in the items,
     * otherwise the customer is charged the subtotal and the verified webhook
     * then fails its amount check and the order is never marked paid.
     */
    protected function buildItems(Order $order): array
    {
        $items = $order->items->map(function ($item) {
            return [
                'id' => mb_substr((string) $item->id, 0, 50),
                'name' => mb_substr((string) $item->product_name, 0, 500),
                'price' => round((float) $item->price * (int) $item->quantity, 2),
            ];
        })->values()->all();

        // An empty items array is rejected by the API.
        if (empty($items)) {
            return [$this->summaryItem($order)];
        }

        $total = round((float) $order->total, 2);
        $remainder = round($total - round(array_sum(array_column($items, 'price')), 2), 2);

        if (abs($remainder) < 0.01) {
            return $items;
        }

        // Tax and shipping push the total up. Add one line for the difference so
        // the customer still sees their goods itemised.
        if ($remainder > 0) {
            $items[] = [
                'id' => mb_substr($order->order_number . '-adj', 0, 50),
                'name' => 'Tax, shipping and discounts',
                'price' => $remainder,
            ];

            return $items;
        }

        // Coupons or referral credit take the total below the sum of the goods.
        // The public API documents a price as a plain non-negative item amount
        // and never mentions negative lines, so rather than risk a rejected
        // session the order is sent as a single exact-total line. Correct
        // charging matters more than an itemised receipt.
        Log::info('HesabPayGateway: Order total is below its item subtotal, sending a single summary line', [
            'order_id' => $order->id,
            'total' => $total,
            'remainder' => $remainder,
        ]);

        return [$this->summaryItem($order)];
    }

    /**
     * Confirm a webhook's signature with HesabPay.
     *
     * HesabPay does not publish a local signing algorithm, so authenticity is
     * established by asking the gateway. Everything downstream depends on this
     * returning true, so failures are fail-closed.
     */
    public function verifyWebhookSignature(array $payload): bool
    {
        $signature = $payload['signature'] ?? null;
        $timestamp = $payload['timestamp'] ?? null;

        if (! $signature || ! $timestamp) {
            Log::warning('HesabPayGateway: Webhook missing signature or timestamp');

            return false;
        }

        try {
            $response = $this->client()->post('/api/v1/hesab/webhooks/verify-signature', [
                'signature' => $signature,
                'timestamp' => (string) $timestamp,
            ]);

            $body = $response->json();

            return $response->successful() && ($body['success'] ?? false) === true;

        } catch (\Exception $e) {
            Log::error('HesabPayGateway: Signature verification call failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Locate the order a webhook refers to.
     */
    public function findOrderForWebhook(array $payload): ?Order
    {
        $reference = $payload['user_id'] ?? null;

        if (! $reference) {
            return null;
        }

        return Order::where('order_number', $reference)->first();
    }

    /**
     * Handle a verified webhook delivery.
     *
     * Idempotent by transaction id: a redelivery is acknowledged without
     * changing the order a second time, so retries cannot double-fulfil.
     */
    public function handleWebhook(array $payload): array
    {
        if (! $this->verifyWebhookSignature($payload)) {
            Log::warning('HesabPayGateway: Rejected webhook with invalid signature', [
                'user_id' => $payload['user_id'] ?? null,
            ]);

            return ['handled' => false, 'message' => 'Invalid signature.'];
        }

        $order = $this->findOrderForWebhook($payload);

        if (! $order) {
            Log::warning('HesabPayGateway: Webhook order not found', [
                'user_id' => $payload['user_id'] ?? null,
            ]);

            return ['handled' => false, 'message' => 'Order not found.'];
        }

        $transactionId = $payload['transaction_id'] ?? null;
        $existing = self::paymentData($order);
        $isSuccess = ($payload['success'] ?? false) === true;

        if (! $isSuccess) {
            // payment_failure. Only downgrade an order that is still unpaid, so
            // a late failure notification cannot undo a completed payment.
            if ($order->payment_status !== Order::PAYMENT_PAID) {
                $order->update(['payment_status' => Order::PAYMENT_FAILED]);
            }

            self::writePaymentData($order, [
                'transaction_id' => $transactionId ?: ($existing['transaction_id'] ?? null),
                'last_webhook' => 'payment_failure',
            ]);

            return ['handled' => true, 'order' => $order, 'outcome' => 'payment_failure'];
        }

        // An order is fulfilled exactly once. Both of these must stop here:
        // a redelivery of the transaction already recorded, and a second
        // distinct transaction that names the same order. The second is real
        // money taken twice, so it is logged loudly for a human to refund, but
        // it must not re-run settlement or send a second confirmation email.
        if ($order->payment_status === Order::PAYMENT_PAID && ! empty($existing['transaction_id'])) {
            $isSameTransaction = $transactionId
                && (string) $existing['transaction_id'] === (string) $transactionId;

            Log::info('HesabPayGateway: Webhook for an already paid order ignored', [
                'order_id' => $order->id,
                'stored_transaction_id' => $existing['transaction_id'],
                'received_transaction_id' => $transactionId,
                'same_transaction' => $isSameTransaction,
            ]);

            if (! $isSameTransaction) {
                Log::critical('HesabPayGateway: A second, different payment arrived for an order that was already paid. Settle it by refunding the duplicate from the HesabPay dashboard.', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'stored_transaction_id' => $existing['transaction_id'],
                    'received_transaction_id' => $transactionId,
                    'received_amount' => $payload['amount'] ?? null,
                ]);
            }

            return ['handled' => true, 'order' => $order, 'outcome' => 'duplicate'];
        }

        // The amount must match the order. Without this check a mismatched or
        // tampered payload could mark an order paid for the wrong sum.
        $expected = round((float) $order->total, 2);
        $received = round((float) ($payload['amount'] ?? 0), 2);

        if (abs($expected - $received) >= 0.01) {
            Log::error('HesabPayGateway: Webhook amount mismatch, order left unpaid', [
                'order_id' => $order->id,
                'expected' => $expected,
                'received' => $received,
            ]);

            return ['handled' => false, 'message' => 'Amount mismatch.'];
        }

        $order->update([
            'payment_status' => Order::PAYMENT_PAID,
            'status' => Order::STATUS_PROCESSING,
        ]);

        self::writePaymentData($order, [
            'transaction_id' => $transactionId,
            'amount' => $received,
            'sender_account' => $payload['sender_account'] ?? null,
            'transaction_date' => $payload['transaction_date'] ?? null,
            'last_webhook' => 'payment_success',
            'paid_at' => now()->toIso8601String(),
        ]);

        Log::info('HesabPayGateway: Payment confirmed by webhook', [
            'order_id' => $order->id,
            'transaction_id' => $transactionId,
            'amount' => $received,
        ]);

        return ['handled' => true, 'order' => $order->fresh(), 'outcome' => 'payment_success'];
    }

    /**
     * {@inheritdoc}
     *
     * The browser redirect is a customer courtesy, not proof of payment. This
     * reports the confirmed state only, and never marks anything paid.
     */
    public function handleCallback(array $data): array
    {
        $orderId = $data['order_id'] ?? null;
        $order = $orderId ? Order::find($orderId) : null;

        if (! $order) {
            return [
                'success' => false,
                'order_id' => null,
                'message' => 'Order not found',
            ];
        }

        $confirmed = $order->payment_status === Order::PAYMENT_PAID
            && ! empty(self::paymentData($order)['transaction_id']);

        return [
            'success' => $confirmed,
            'order_id' => $order->id,
            'message' => $confirmed
                ? 'Payment confirmed.'
                : 'Waiting for payment confirmation from HesabPay.',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function verifyPayment(Order $order): bool
    {
        return $order->payment_status === Order::PAYMENT_PAID
            && ! empty(self::paymentData($order)['transaction_id']);
    }

    /**
     * {@inheritdoc}
     *
     * The public HesabPay API reference documents no refund endpoint, so this
     * reports the truth rather than a false success. Refunds are made from the
     * HesabPay dashboard.
     */
    public function refund(Order $order, ?float $amount = null, ?string $reason = null): array
    {
        Log::warning('HesabPayGateway: Refund requested but not supported via API', [
            'order_id' => $order->id,
            'amount' => $amount,
        ]);

        return [
            'success' => false,
            'transaction_id' => '',
            'message' => 'HesabPay refunds are done from the HesabPay dashboard. No refund was issued from the store.',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigFields(): array
    {
        return [
            [
                'name' => 'mode',
                'label' => 'Environment',
                'type' => 'select',
                'required' => true,
                'default' => 'sandbox',
                'options' => [
                    'sandbox' => 'Sandbox (no real charges)',
                    'production' => 'Production (real charges)',
                ],
                'description' => 'Sandbox uses api-sandbox.hesab.com. Production uses api.hesab.com.',
            ],
            [
                'name' => 'test_api_key',
                'label' => 'Sandbox API Key',
                'type' => 'password',
                'required' => false,
                'placeholder' => 'Paste the key from developers-sandbox.hesab.com',
                'description' => 'From the HesabPay sandbox dashboard. Leave empty to use the HESABPAY_API_KEY environment variable.',
            ],
            [
                'name' => 'api_key',
                'label' => 'Live API Key',
                'type' => 'password',
                'required' => false,
                'placeholder' => 'Paste the key from developers.hesab.com',
                'description' => 'From the HesabPay production dashboard. Never exposed to the browser.',
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function isConfigured(): bool
    {
        return $this->getApiKey() !== null;
    }
}
