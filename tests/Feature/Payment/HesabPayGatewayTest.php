<?php

use Cartxis\Core\Models\PaymentMethod;
use Cartxis\Core\Services\PaymentGatewayManager;
use Cartxis\HesabPay\Services\HesabPayGateway;
use Cartxis\Sales\Models\Transaction;
use Cartxis\Shop\Models\Order;
use Illuminate\Support\Facades\Http;

/**
 * Helpers for building the little bit of order state the gateway touches.
 */
function hesabpayOrder(array $attributes = []): Order
{
    static $counter = 0;
    $counter++;

    return Order::create(array_merge([
        'order_number' => 'HSP-' . str_pad((string) $counter, 6, '0', STR_PAD_LEFT),
        'status' => Order::STATUS_PENDING,
        'payment_status' => Order::PAYMENT_PENDING,
        'payment_method' => 'hesabpay',
        'subtotal' => 100,
        'tax' => 0,
        'shipping_cost' => 0,
        'discount' => 0,
        'total' => 100,
        'customer_email' => 'shopper@example.com',
        'source_channel' => 'web',
    ], $attributes));
}

function hesabpayItem(Order $order, string $name = 'Test product', int $quantity = 2, float $price = 50.0): void
{
    $order->items()->create([
        'product_name' => $name,
        'quantity' => $quantity,
        'price' => $price,
        'total' => $quantity * $price,
    ]);
}

function activateHesabPay(array $configuration = []): PaymentMethod
{
    $method = PaymentMethod::firstOrCreate(
        ['code' => 'hesabpay'],
        ['name' => 'HesabPay', 'type' => 'hesabpay', 'is_active' => false, 'sort_order' => 3]
    );

    $method->update([
        'is_active' => true,
        'configuration' => array_merge([
            'mode' => 'sandbox',
            'test_api_key' => 'sandbox-key',
            'api_key' => 'live-key',
        ], $configuration),
    ]);

    return $method->fresh();
}

/**
 * A webhook body the signature endpoint will be faked to accept.
 */
function hesabpayWebhookPayload(Order $order, array $overrides = []): array
{
    return array_merge([
        'status_code' => 10,
        'success' => true,
        'user_id' => $order->order_number,
        'transaction_id' => 'txn_' . $order->order_number,
        'amount' => 100,
        'signature' => 'sig_abc123',
        'timestamp' => '1707719607',
        'transaction_date' => '2024-02-12 11:04:28',
        'sender_account' => '793111222',
    ], $overrides);
}

function fakeSignatureValid(bool $valid = true): void
{
    Http::fake([
        'api-sandbox.hesab.com/api/v1/hesab/webhooks/verify-signature' => Http::response([
            'success' => $valid,
        ]),
    ]);
}

// ---------------------------------------------------------------------------
// Registration and seeding
// ---------------------------------------------------------------------------

it('registers the gateway and its routes', function () {
    $manager = app(PaymentGatewayManager::class);

    expect($manager->has('hesabpay'))->toBeTrue();
    expect($manager->get('hesabpay'))->toBeInstanceOf(HesabPayGateway::class);

    $names = collect(app('router')->getRoutes())->map(fn ($r) => $r->getName());

    expect($names)->toContain('hesabpay.webhook');
    expect($names)->toContain('hesabpay.return.success');
    expect($names)->toContain('hesabpay.return.failure');
});

it('ships switched on and preselected as the default method', function () {
    $method = PaymentMethod::where('code', 'hesabpay')->first();

    expect($method)->not->toBeNull();
    expect($method->type)->toBe('hesabpay');
    expect($method->is_active)->toBeTrue();
    expect($method->is_default)->toBeTrue();

    // Ahead of Cash on Delivery, which sits at 1. The storefront preselects
    // the default first and only falls back to sort_order, so a wallet that
    // was meant to be the default but sorted behind COD would never appear as
    // the chosen option.
    expect($method->sort_order)->toBe(0);
});

it('is not configured until an api key exists', function () {
    $gateway = app(PaymentGatewayManager::class)->get('hesabpay');

    PaymentMethod::where('code', 'hesabpay')->update([
        'is_active' => true,
        'configuration' => ['mode' => 'sandbox', 'test_api_key' => '', 'api_key' => ''],
    ]);

    expect($gateway->isConfigured())->toBeFalse();
});

// ---------------------------------------------------------------------------
// Creating a checkout session
// ---------------------------------------------------------------------------

it('redirects the shopper to the hosted checkout', function () {
    activateHesabPay();

    Http::fake([
        'api-sandbox.hesab.com/api/v1/payment/create-session' => Http::response([
            'status_code' => 10,
            'success' => true,
            'message' => 'Payment session created successfully',
            'url' => 'https://checkout.hesab.test/session/abc123',
        ]),
    ]);

    $order = hesabpayOrder();
    hesabpayItem($order);

    $gateway = app(PaymentGatewayManager::class)->get('hesabpay');
    $response = $gateway->processPayment($order);

    expect($response)->toBeInstanceOf(Illuminate\Http\RedirectResponse::class);
    expect($response->getTargetUrl())->toBe('https://checkout.hesab.test/session/abc123');
});

it('sends the api key, our order reference and the multiplied line items', function () {
    activateHesabPay();

    Http::fake([
        'api-sandbox.hesab.com/api/v1/payment/create-session' => Http::response([
            'success' => true,
            'url' => 'https://checkout.hesab.test/session/abc123',
        ]),
    ]);

    // Totals match the line below, so the only thing being checked here is the
    // quantity multiplication, not the tax and shipping adjustment.
    $order = hesabpayOrder(['subtotal' => 120, 'total' => 120]);
    hesabpayItem($order, 'Running shoes', 3, 40.00);

    app(PaymentGatewayManager::class)->get('hesabpay')->processPayment($order);

    Http::assertSent(function ($request) use ($order) {
        $body = $request->data();

        // Authentication header must be the documented scheme.
        expect($request->header('Authorization')[0])->toBe('API-KEY sandbox-key');

        // This is the value the webhook echoes back, so it must be our order.
        expect($body['user_id'])->toBe($order->order_number);
        expect($body['email'])->toBe('shopper@example.com');

        // HesabPay has no quantity field, so it has to be multiplied out.
        expect($body['items'])->toHaveCount(1);
        expect($body['items'][0]['name'])->toBe('Running shoes');
        expect($body['items'][0]['price'])->toBe(120.0);

        expect($body['redirect_success_url'])->toContain('/hesabpay/return/success/' . $order->id);
        expect($body['redirect_failure_url'])->toContain('/hesabpay/return/failure/' . $order->id);

        return true;
    });
});

it('records the session on the order without clobbering other gateway data', function () {
    activateHesabPay();

    Http::fake([
        'api-sandbox.hesab.com/api/v1/payment/create-session' => Http::response([
            'success' => true,
            'url' => 'https://checkout.hesab.test/session/abc123',
        ]),
    ]);

    $order = hesabpayOrder();
    hesabpayItem($order);
    $order->update(['payment_data' => ['cod' => ['reference' => 'keep-me']]]);

    app(PaymentGatewayManager::class)->get('hesabpay')->processPayment($order);

    $data = $order->fresh()->payment_data;

    expect($data['cod']['reference'])->toBe('keep-me');
    expect($data['hesabpay']['checkout_url'])->toBe('https://checkout.hesab.test/session/abc123');
    expect($data['hesabpay']['order_reference'])->toBe($order->order_number);
});

it('does not throw a red error page when hesabpay rejects the session', function () {
    activateHesabPay();

    Http::fake([
        'api-sandbox.hesab.com/api/v1/payment/create-session' => Http::response([
            'success' => false,
            'message' => 'Invalid request payload',
        ], 400),
    ]);

    $order = hesabpayOrder();
    hesabpayItem($order);

    $result = app(PaymentGatewayManager::class)->get('hesabpay')->processPayment($order);

    expect($result)->toBeArray();
    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe('Invalid request payload');
});

// ---------------------------------------------------------------------------
// The amount actually charged must be the order total
// ---------------------------------------------------------------------------

it('adds tax and shipping into the session items so the charged amount is the order total', function () {
    activateHesabPay();

    Http::fake([
        'api-sandbox.hesab.com/api/v1/payment/create-session' => Http::response([
            'success' => true,
            'url' => 'https://checkout.hesab.test/session/abc123',
        ]),
    ]);

    // HesabPay charges the sum of the item prices, but orders.total also carries
    // tax and shipping. If those are left out of the session the customer is
    // charged the subtotal and the later webhook fails its amount check.
    $order = hesabpayOrder([
        'subtotal' => 100,
        'tax' => 10,
        'shipping_cost' => 15,
        'total' => 125,
    ]);
    hesabpayItem($order, 'Desk lamp', 1, 100.00);

    app(PaymentGatewayManager::class)->get('hesabpay')->processPayment($order);

    Http::assertSent(function ($request) use ($order) {
        $items = $request->data()['items'];

        expect(round(array_sum(array_column($items, 'price')), 2))->toBe(125.0)
            ->and(round((float) $order->total, 2))->toBe(125.0);

        // The goods are still itemised, not collapsed into one line.
        expect($items[0]['name'])->toBe('Desk lamp');

        return true;
    });
});

it('charges the exact total when a discount makes it lower than the item subtotal', function () {
    activateHesabPay();

    Http::fake([
        'api-sandbox.hesab.com/api/v1/payment/create-session' => Http::response([
            'success' => true,
            'url' => 'https://checkout.hesab.test/session/abc123',
        ]),
    ]);

    $order = hesabpayOrder([
        'subtotal' => 100,
        'discount' => 20,
        'total' => 80,
    ]);
    hesabpayItem($order, 'Desk lamp', 1, 100.00);

    app(PaymentGatewayManager::class)->get('hesabpay')->processPayment($order);

    Http::assertSent(function ($request) {
        $items = $request->data()['items'];

        expect(round(array_sum(array_column($items, 'price')), 2))->toBe(80.0);

        return true;
    });
});

it('refuses to open a session for an order that costs nothing', function () {
    activateHesabPay();

    Http::fake([
        'api-sandbox.hesab.com/api/v1/payment/create-session' => Http::response([
            'success' => true,
            'url' => 'https://checkout.hesab.test/session/abc123',
        ]),
    ]);

    $order = hesabpayOrder(['subtotal' => 100, 'discount' => 100, 'total' => 0]);
    hesabpayItem($order);

    $result = app(PaymentGatewayManager::class)->get('hesabpay')->processPayment($order);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('zero');

    Http::assertNothingSent();
});

// ---------------------------------------------------------------------------
// Misconfiguration is reported as misconfiguration
// ---------------------------------------------------------------------------

it('tells the shopper the store is not set up rather than blaming the network', function () {
    activateHesabPay(['mode' => 'sandbox', 'test_api_key' => '', 'api_key' => '']);

    Http::fake([
        'api-sandbox.hesab.com/*' => Http::response(['success' => true]),
    ]);

    $order = hesabpayOrder();
    hesabpayItem($order);

    $result = app(PaymentGatewayManager::class)->get('hesabpay')->processPayment($order);

    expect($result['success'])->toBeFalse();
    // A missing key is the store owner's problem, not a flaky connection, and
    // telling the shopper to "try again" would send them into a dead end.
    expect($result['message'])->toContain('not set up');
    expect($result['message'])->not->toContain('Could not reach');

    Http::assertNothingSent();
});

// ---------------------------------------------------------------------------
// Webhooks are the only thing that marks an order paid
// ---------------------------------------------------------------------------

it('rejects a webhook whose signature does not verify', function () {
    activateHesabPay();
    fakeSignatureValid(false);

    $order = hesabpayOrder();

    $this->postJson('/webhooks/hesabpay', hesabpayWebhookPayload($order))
        ->assertStatus(400);

    expect($order->fresh()->payment_status)->toBe(Order::PAYMENT_PENDING);
});

it('rejects a webhook with no signature at all', function () {
    activateHesabPay();
    fakeSignatureValid(true);

    $order = hesabpayOrder();

    $payload = hesabpayWebhookPayload($order);
    unset($payload['signature']);

    $this->postJson('/webhooks/hesabpay', $payload)->assertStatus(400);

    expect($order->fresh()->payment_status)->toBe(Order::PAYMENT_PENDING);
});

it('marks the order paid once the signature verifies and the amount matches', function () {
    activateHesabPay();
    fakeSignatureValid(true);

    $order = hesabpayOrder();
    hesabpayItem($order);

    $this->postJson('/webhooks/hesabpay', hesabpayWebhookPayload($order))
        ->assertStatus(200);

    $order->refresh();

    expect($order->payment_status)->toBe(Order::PAYMENT_PAID);
    expect($order->status)->toBe(Order::STATUS_PROCESSING);

    $data = $order->payment_data;
    expect($data['hesabpay']['transaction_id'])->toBe('txn_' . $order->order_number);
    expect($data['hesabpay']['last_webhook'])->toBe('payment_success');
});

it('leaves the order unpaid when the amount does not match', function () {
    activateHesabPay();
    fakeSignatureValid(true);

    $order = hesabpayOrder();

    $this->postJson('/webhooks/hesabpay', hesabpayWebhookPayload($order, ['amount' => 5]))
        ->assertStatus(400);

    expect($order->fresh()->payment_status)->toBe(Order::PAYMENT_PENDING);
});

it('ignores a redelivered webhook instead of fulfilling twice', function () {
    activateHesabPay();
    fakeSignatureValid(true);

    $order = hesabpayOrder();
    hesabpayItem($order);

    $payload = hesabpayWebhookPayload($order);

    $this->postJson('/webhooks/hesabpay', $payload)->assertStatus(200);
    $this->postJson('/webhooks/hesabpay', $payload)->assertStatus(200);

    $order->refresh();

    // Still one confirmed transaction, still one recorded transaction id, and
    // still one ledger entry for the order.
    expect($order->payment_status)->toBe(Order::PAYMENT_PAID);
    expect($order->payment_data['hesabpay']['transaction_id'])->toBe($payload['transaction_id']);
    expect(Transaction::where('order_id', $order->id)->where('gateway', 'hesabpay')->count())->toBe(1);
});

it('does not settle a second, different payment for an order that is already paid', function () {
    activateHesabPay();
    fakeSignatureValid(true);

    $order = hesabpayOrder();
    hesabpayItem($order);

    $first = hesabpayWebhookPayload($order, ['transaction_id' => 'txn_first', 'amount' => 100]);

    $this->postJson('/webhooks/hesabpay', $first)->assertStatus(200);

    // The customer pays twice, or HesabPay reuses the reference. A different
    // transaction id naming an already paid order is money that has to be
    // refunded by hand, so it must not be booked as a second payment.
    $second = hesabpayWebhookPayload($order, ['transaction_id' => 'txn_second', 'amount' => 100]);

    $this->postJson('/webhooks/hesabpay', $second)->assertStatus(200);

    $order->refresh();

    expect($order->payment_data['hesabpay']['transaction_id'])->toBe('txn_first');
    expect(Transaction::where('order_id', $order->id)->where('gateway', 'hesabpay')->count())->toBe(1);
});

it('records a failure webhook without clobbering an already paid order', function () {
    activateHesabPay();
    fakeSignatureValid(true);

    $order = hesabpayOrder();
    $order->update(['payment_status' => Order::PAYMENT_PAID]);

    $this->postJson('/webhooks/hesabpay', hesabpayWebhookPayload($order, [
        'success' => false,
    ]))->assertStatus(200);

    // A late failure notice must not undo money that already arrived.
    expect($order->fresh()->payment_status)->toBe(Order::PAYMENT_PAID);
});

it('rejects a webhook for an order it cannot find', function () {
    activateHesabPay();
    fakeSignatureValid(true);

    $this->postJson('/webhooks/hesabpay', hesabpayWebhookPayload(
        hesabpayOrder(),
        ['user_id' => 'does-not-exist']
    ))->assertStatus(400);
});

// ---------------------------------------------------------------------------
// The browser redirect proves nothing
// ---------------------------------------------------------------------------

it('does not mark the order paid when the shopper returns from checkout', function () {
    activateHesabPay();

    $order = hesabpayOrder();
    hesabpayItem($order);

    $this->get('/hesabpay/return/success/' . $order->id)
        ->assertRedirect(route('shop.checkout.success', ['order' => $order->id]));

    // Payment is not confirmed until the webhook arrives.
    expect($order->fresh()->payment_status)->toBe(Order::PAYMENT_PENDING);
});

it('keeps the basket after a failed attempt so the shopper can retry', function () {
    activateHesabPay();

    $order = hesabpayOrder();
    hesabpayItem($order);

    session(['cart' => [['id' => 1, 'product_id' => 1, 'quantity' => 1, 'price' => 50]]]);

    $this->get('/hesabpay/return/failure/' . $order->id)
        ->assertRedirect(route('shop.checkout.success', ['order' => $order->id]));

    expect(session('cart'))->not->toBeNull();
    expect($order->fresh()->payment_status)->toBe(Order::PAYMENT_FAILED);
});

// ---------------------------------------------------------------------------
// Refunds
// ---------------------------------------------------------------------------

it('reports that refunds must be done in the hesabpay dashboard', function () {
    activateHesabPay();

    $order = hesabpayOrder();

    $result = app(PaymentGatewayManager::class)->get('hesabpay')->refund($order, 50.0);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('HesabPay dashboard');
});
