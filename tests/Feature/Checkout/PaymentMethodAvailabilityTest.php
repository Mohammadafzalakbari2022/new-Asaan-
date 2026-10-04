<?php

use Cartxis\Core\Models\PaymentMethod;
use Cartxis\Core\Models\ShippingMethod;
use Cartxis\Product\Models\Product;
use Cartxis\Shop\Models\Order;

/**
 * The storefront checkout list is the single place that decides which payment
 * options a shopper is offered, so these tests drive it through HTTP rather
 * than through the controller.
 */

/**
 * A product in the basket, priced so a 100-unit order clears any minimum.
 */
function checkoutAvailabilityCart(): Product
{
    $product = Product::create([
        'name' => 'Availability test product',
        'price' => 100,
        'quantity' => 10,
        'status' => 'active',
        'type' => 'simple',
        'manage_stock' => true,
    ]);

    session(['cart' => [[
        'id' => $product->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'price' => 100,
    ]]]);

    return $product;
}

/**
 * Cash on Delivery, the fallback every store ships with.
 */
function checkoutAvailabilityCod(): PaymentMethod
{
    return PaymentMethod::create([
        'code' => 'cod',
        'name' => 'Cash on Delivery',
        'type' => 'cod',
        'is_active' => true,
        'is_default' => false,
        'sort_order' => 1,
    ]);
}

function checkoutAvailabilityShippingMethod(): ShippingMethod
{
    return ShippingMethod::create([
        'name' => 'Flat rate delivery',
        'slug' => 'flat-rate-delivery',
        'type' => 'flat-rate',
        'status' => 'active',
        'base_cost' => 10,
    ]);
}

/**
 * Give HesabPay the keys the owner would paste into the admin screen.
 */
function configureHesabPayKeys(): PaymentMethod
{
    PaymentMethod::where('code', 'hesabpay')->update([
        'is_active' => true,
        'configuration' => [
            'mode' => 'sandbox',
            'test_api_key' => 'sandbox-key',
            'api_key' => '',
        ],
    ]);

    return PaymentMethod::where('code', 'hesabpay')->first();
}

/**
 * The payment methods checkout actually rendered, in the order it rendered
 * them.
 */
function checkoutPaymentMethods(): array
{
    $response = test()->get(route('shop.checkout.index'))->assertSuccessful();

    return $response->viewData('page')['props']['paymentMethods'];
}

/**
 * The method the storefront preselects: the default if one survived the
 * filters, otherwise the first option. This mirrors Checkout/Index.vue.
 */
function checkoutPreselectedMethod(array $methods): ?string
{
    $default = collect($methods)->firstWhere('is_default', true);

    return $default['code'] ?? $methods[0]['code'] ?? null;
}

// ---------------------------------------------------------------------------
// An active method whose gateway has no keys cannot take a payment
// ---------------------------------------------------------------------------

it('hides an active wallet that has no api key', function () {
    checkoutAvailabilityCart();
    checkoutAvailabilityCod();

    // Exactly how it ships: active, default, and with blank keys.
    $method = PaymentMethod::where('code', 'hesabpay')->first();

    expect($method->is_active)->toBeTrue()
        ->and($method->is_default)->toBeTrue()
        ->and($method->getConfigValue('test_api_key'))->toBe('');

    $codes = array_column(checkoutPaymentMethods(), 'code');

    // Offering it would send every shopper to the option that cannot work.
    expect($codes)->not->toContain('hesabpay');
    expect($codes)->toContain('cod');
});

it('falls back to the next method when the default has no api key', function () {
    checkoutAvailabilityCart();
    checkoutAvailabilityCod();

    // The storefront picks the default first and only then falls back to the
    // first option in the list, so dropping the default has to leave something
    // selectable behind it.
    expect(checkoutPreselectedMethod(checkoutPaymentMethods()))->toBe('cod');
});

it('offers the wallet as the default once the owner configures it', function () {
    checkoutAvailabilityCart();
    checkoutAvailabilityCod();
    configureHesabPayKeys();

    $methods = checkoutPaymentMethods();

    expect(array_column($methods, 'code'))->toContain('hesabpay', 'cod')
        ->and(checkoutPreselectedMethod($methods))->toBe('hesabpay');
});

it('still offers the methods that have no gateway behind them', function () {
    checkoutAvailabilityCart();
    checkoutAvailabilityCod();

    // COD and Bank Transfer are settled by hand. There is no gateway to ask
    // whether they are configured, so the check must not filter them out.
    $codes = array_column(checkoutPaymentMethods(), 'code');

    expect($codes)->toContain('cod');
});

// ---------------------------------------------------------------------------
// A hand-written request cannot order against a switched-off method
// ---------------------------------------------------------------------------

function checkoutAvailabilityPayload(ShippingMethod $shipping, string $paymentMethod): array
{
    return [
        'email' => 'shopper@example.test',
        'shipping_address' => [
            'first_name' => 'Ali',
            'last_name' => 'Ahmadi',
            'address_line1' => '1 Test Street',
            'city' => 'Kabul',
            'state' => 'Kabul',
            'postal_code' => '10001',
            'country' => 'AF',
            'phone' => '+93700000000',
        ],
        'shipping_method_id' => $shipping->id,
        'payment_method' => $paymentMethod,
        'billing_same_as_shipping' => true,
        'terms_accepted' => true,
    ];
}

it('refuses an order against a method the owner has switched off', function () {
    checkoutAvailabilityCart();
    $shipping = checkoutAvailabilityShippingMethod();

    PaymentMethod::where('code', 'hesabpay')->update(['is_active' => false]);

    $this->post(route('shop.checkout.store'), checkoutAvailabilityPayload($shipping, 'hesabpay'))
        ->assertSessionHasErrors('payment_method');

    // Nothing recorded: the order row is only written after this passes.
    expect(Order::count())->toBe(0);
});

it('refuses a payment method that does not exist at all', function () {
    checkoutAvailabilityCart();
    $shipping = checkoutAvailabilityShippingMethod();

    // With no row there is no gateway either, and the checkout used to treat
    // that as a manual payment and mark the order paid on the spot.
    $this->post(route('shop.checkout.store'), checkoutAvailabilityPayload($shipping, 'invented-method'))
        ->assertSessionHasErrors('payment_method');

    expect(Order::count())->toBe(0);
});

it('still places an order against a switched-on method', function () {
    checkoutAvailabilityCart();
    checkoutAvailabilityCod();
    $shipping = checkoutAvailabilityShippingMethod();

    $this->post(route('shop.checkout.store'), checkoutAvailabilityPayload($shipping, 'cod'))
        ->assertSessionHasNoErrors();

    expect(Order::count())->toBe(1)
        ->and(Order::first()->payment_method)->toBe('cod');
});

// ---------------------------------------------------------------------------
// The store offers Bank Transfer, HesabPay and Cash on Delivery only
// ---------------------------------------------------------------------------

it('never offers a payment method outside the store three', function () {
    checkoutAvailabilityCart();
    checkoutAvailabilityCod();

    // An active row with no registered gateway: without the supported-code
    // allow-list the checkout would offer it, because there is no gateway to
    // ask whether it is configured.
    PaymentMethod::create([
        'code' => 'paystack',
        'name' => 'Paystack',
        'type' => 'other',
        'is_active' => true,
        'is_default' => false,
        'sort_order' => 9,
    ]);

    $codes = array_column(checkoutPaymentMethods(), 'code');

    expect($codes)->not->toContain('paystack')
        ->and($codes)->toContain('cod')
        ->and(PaymentMethod::supported()->pluck('code')->all())->not->toContain('paystack');
});
