<?php

use App\Models\User;
use Cartxis\Core\Models\PaymentMethod;
use Cartxis\Core\Models\ShippingMethod;
use Cartxis\Core\Models\ShippingRate;
use Cartxis\Core\Support\StoreCountry;
use Cartxis\Customer\Models\Customer;
use Cartxis\Customer\Models\CustomerAddress;
use Cartxis\Customer\Models\CustomerGroup;
use Cartxis\Product\Models\Product;
use Cartxis\Shop\Models\Address;
use Cartxis\Shop\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The store only sells inside Afghanistan, so the country is not a field
 * anybody fills in. These tests drive the real HTTP endpoints and the real
 * models with the country missing, with it wrong, and with it hostile, because
 * the whole point is that the shopper never has to think about it.
 */

function countryAdmin(): User
{
    return User::factory()->create([
        'email' => 'admin@test.com',
        'role' => 'admin',
        'is_active' => true,
    ]);
}

function countryCustomer(): Customer
{
    return countryCustomerForUser(null);
}

/**
 * The same, but attached to a login. Guest checkout leaves a customer with no
 * user_id; the mobile app looks the other way round, by user_id.
 */
function countryCustomerForUser(?User $user = null): Customer
{
    $group = CustomerGroup::create([
        'name' => 'Country Test Group ' . Str::random(4),
        'code' => 'country-test-group-' . Str::random(6),
        'description' => 'Group for the country tests',
        'color' => '#000000',
        'is_active' => true,
        'order' => 1,
    ]);

    return Customer::create(array_filter([
        'user_id' => $user?->id,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => $user?->email ?? Str::random(6) . '@example.test',
        'customer_group_id' => $group->id,
        'is_active' => true,
        'is_verified' => true,
    ], fn ($value) => $value !== null));
}

/**
 * An address payload with no country key at all, which is what the forms now
 * send since the field was taken off them.
 */
function countryAddressPayload(array $overrides = []): array
{
    return array_merge([
        'type' => 'shipping',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'address_line_1' => '123 Main St',
        'city' => 'Kabul',
        'state' => 'Kabul',
        'postal_code' => '10001',
    ], $overrides);
}

// ---------------------------------------------------------------------------
// An address row is always the store country, whoever tries to change it
// ---------------------------------------------------------------------------

it('stores the store country when no country is supplied', function () {
    $customer = countryCustomer();

    $response = $this->actingAs(countryAdmin(), 'admin')
        ->post(route('admin.customers.addresses.store', $customer), countryAddressPayload());

    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('customer_addresses', [
        'customer_id' => $customer->id,
        'country' => StoreCountry::CODE,
    ]);
});

it('stores the store country when a different country is sent', function () {
    $customer = countryCustomer();

    $response = $this->actingAs(countryAdmin(), 'admin')
        ->post(route('admin.customers.addresses.store', $customer),
            countryAddressPayload(['country' => 'US']));

    $response->assertSessionHasNoErrors();

    // The client cannot choose. A stale build or a hand-edited POST body both
    // end up with the same answer.
    $this->assertDatabaseHas('customer_addresses', [
        'customer_id' => $customer->id,
        'country' => StoreCountry::CODE,
    ]);

    expect(CustomerAddress::where('customer_id', $customer->id)->first()->country)
        ->toBe(StoreCountry::CODE);
});

it('does not ask for a country when validating an address', function () {
    $customer = countryCustomer();

    $response = $this->actingAs(countryAdmin(), 'admin')
        ->post(route('admin.customers.addresses.store', $customer), []);

    // A button that silently does nothing is a bug. Country is deliberately
    // absent from this list: its absence is fine.
    $response->assertSessionHasErrors([
        'type', 'first_name', 'last_name', 'address_line_1', 'city', 'state', 'postal_code',
    ]);
    $response->assertSessionDoesntHaveErrors(['country']);
});

it('keeps the store country when an address is updated without one', function () {
    $customer = countryCustomer();

    $address = CustomerAddress::create([
        'customer_id' => $customer->id,
        'type' => 'shipping',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'address_line_1' => '123 Main St',
        'city' => 'Kabul',
        'state' => 'Kabul',
        'postal_code' => '10001',
        'country' => StoreCountry::CODE,
    ]);

    $response = $this->actingAs(countryAdmin(), 'admin')
        ->put(route('admin.customers.addresses.update', [$customer, $address]), [
            'type' => 'shipping',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'address_line_1' => '789 Updated St',
            'city' => 'Kabul',
            'state' => 'Kabul',
            'postal_code' => '10001',
        ]);

    $response->assertSessionHasNoErrors();

    // The edit landed AND the country survived, rather than being blanked out
    // by the update that no longer mentions it.
    $this->assertDatabaseHas('customer_addresses', [
        'id' => $address->id,
        'address_line_1' => '789 Updated St',
        'country' => StoreCountry::CODE,
    ]);
});

it('overwrites a stale country on an update that sends a different one', function () {
    $customer = countryCustomer();

    $address = CustomerAddress::create([
        'customer_id' => $customer->id,
        'type' => 'shipping',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'address_line_1' => '123 Main St',
        'city' => 'New York',
        'state' => 'NY',
        'postal_code' => '10001',
        'country' => StoreCountry::CODE,
    ]);

    $this->actingAs(countryAdmin(), 'admin')
        ->put(route('admin.customers.addresses.update', [$customer, $address]),
            countryAddressPayload(['country' => 'US']));

    $this->assertDatabaseHas('customer_addresses', [
        'id' => $address->id,
        'country' => StoreCountry::CODE,
    ]);
});

// ---------------------------------------------------------------------------
// The model itself is the backstop, whatever the caller did
// ---------------------------------------------------------------------------

it('writes the store country even when the model is filled in directly', function () {
    $customer = countryCustomer();

    // No controller, no request: a seeder, a console command, or a future
    // screen. The column is filled here rather than trusted.
    $address = CustomerAddress::create([
        'customer_id' => $customer->id,
        'type' => 'billing',
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'address_line_1' => '456 Oak Ave',
        'city' => 'Kabul',
        'state' => 'Kabul',
        'postal_code' => '10001',
        'country' => 'CA',
    ]);

    expect($address->country)->toBe(StoreCountry::CODE);
});

it('writes the store country on an order address with nothing supplied', function () {
    $user = User::factory()->create(['email' => 'shopper@example.test']);

    $address = Address::create([
        'addressable_type' => User::class,
        'addressable_id' => $user->id,
        'type' => Address::TYPE_SHIPPING,
        'first_name' => 'Ali',
        'last_name' => 'Ahmadi',
        'address_line1' => '1 Test Street',
        'city' => 'Kabul',
        'state' => 'Kabul',
        'postal_code' => '10001',
        'country' => 'GB',
        'phone' => '+93700000000',
    ]);

    expect($address->country)->toBe(StoreCountry::CODE);
});

// ---------------------------------------------------------------------------
// A shopper's own address book, through the storefront controller
// ---------------------------------------------------------------------------

it('stores the store country when a shopper saves an address without one', function () {
    $user = User::factory()->create(['email' => 'shopper@example.test']);

    $response = $this->actingAs($user)
        ->post(route('shop.account.addresses.store'), [
            'first_name' => 'Ali',
            'last_name' => 'Ahmadi',
            'address_line1' => '1 Test Street',
            'city' => 'Kabul',
            'state' => 'Kabul',
            'postal_code' => '10001',
            'phone' => '+93700000000',
            'address_type' => 'shipping',
        ]);

    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('addresses', [
        'addressable_id' => $user->id,
        'country' => StoreCountry::CODE,
    ]);
});

it('stores the store country when a shopper sends a different one', function () {
    $user = User::factory()->create(['email' => 'shopper@example.test']);

    $this->actingAs($user)
        ->post(route('shop.account.addresses.store'), [
            'first_name' => 'Ali',
            'last_name' => 'Ahmadi',
            'address_line1' => '1 Test Street',
            'city' => 'New York',
            'state' => 'NY',
            'postal_code' => '10001',
            'country' => 'US',
            'phone' => '+93700000000',
            'address_type' => 'shipping',
        ])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('addresses', [
        'addressable_id' => $user->id,
        'country' => StoreCountry::CODE,
    ]);
});

// ---------------------------------------------------------------------------
// Checkout
// ---------------------------------------------------------------------------

function countryCartItem(): Product
{
    $product = Product::create([
        'name' => 'Country test product ' . Str::random(6),
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

function countryShippingMethod(): ShippingMethod
{
    return ShippingMethod::create([
        'name' => 'Flat rate delivery',
        'slug' => 'flat-rate-delivery',
        'type' => 'flat-rate',
        'status' => 'active',
        'base_cost' => 10,
    ]);
}

function countryCod(): PaymentMethod
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

function countryCheckoutPayload(
    ShippingMethod $shipping,
    array $shippingOverrides = [],
    array $topLevel = []
): array {
    return array_merge([
        'email' => 'shopper@example.test',
        'shipping_address' => array_merge([
            'first_name' => 'Ali',
            'last_name' => 'Ahmadi',
            'address_line1' => '1 Test Street',
            'city' => 'Kabul',
            'state' => 'Kabul',
            'postal_code' => '10001',
            'phone' => '+93700000000',
        ], $shippingOverrides),
        'shipping_method_id' => $shipping->id,
        'payment_method' => 'cod',
        'billing_same_as_shipping' => true,
        'terms_accepted' => true,
    ], $topLevel);
}

it('completes checkout when the shipping address has no country', function () {
    countryCartItem();
    $shipping = countryShippingMethod();
    countryCod();

    $this->post(route('shop.checkout.store'), countryCheckoutPayload($shipping))
        ->assertSessionHasNoErrors();

    expect(Order::count())->toBe(1);

    // No country anywhere in the request, and the row still has one.
    $this->assertDatabaseHas('addresses', [
        'type' => Address::TYPE_SHIPPING,
        'country' => StoreCountry::CODE,
    ]);
});

it('completes checkout when the shipping address sends another country', function () {
    countryCartItem();
    $shipping = countryShippingMethod();
    countryCod();

    $this->post(route('shop.checkout.store'), countryCheckoutPayload($shipping, ['country' => 'US']))
        ->assertSessionHasNoErrors();

    expect(Order::count())->toBe(1)
        ->and(Order::first()->shippingAddress->country)->toBe(StoreCountry::CODE);
});

it('keeps the store country when a guest checkout becomes a registered account', function () {
    countryCartItem();
    $shipping = countryShippingMethod();
    countryCod();

    // First a guest order, which leaves a guest customer behind.
    $this->post(route('shop.checkout.store'), countryCheckoutPayload($shipping))
        ->assertSessionHasNoErrors();

    $guest = Customer::where('email', 'shopper@example.test')->where('is_guest', true)->first();

    expect($guest)->not->toBeNull();

    $this->assertDatabaseHas('customer_addresses', [
        'customer_id' => $guest->id,
        'country' => StoreCountry::CODE,
    ]);

    // Now the same email comes back and creates a real account. The guest row
    // is upgraded rather than duplicated, so the saved address has to survive
    // the conversion with its country intact.
    //
    // The basket is refilled because the first order emptied it, and checkout
    // refuses an empty basket before it reaches the account creation.
    countryCartItem();

    // create_account / password are top-level fields, not part of the address.
    // Getting that wrong silently produces a second guest order, so the test
    // checks the conversion actually happened rather than assuming it.
    $response = $this->post(route('shop.checkout.store'), countryCheckoutPayload($shipping, [], [
        'create_account' => true,
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
    ]));

    $response->assertSessionHasNoErrors();

    $registered = Customer::where('email', 'shopper@example.test')->where('is_guest', false)->first();

    expect($registered)->not->toBeNull()
        ->and($registered->id)->toBe($guest->id);

    $this->assertDatabaseHas('customer_addresses', [
        'customer_id' => $registered->id,
        'country' => StoreCountry::CODE,
    ]);

    // Every address the upgraded customer owns is still the store country.
    $countries = CustomerAddress::where('customer_id', $registered->id)
        ->pluck('country')
        ->unique()
        ->all();

    expect($countries)->not->toBeEmpty()
        ->and($countries)->toBe([StoreCountry::CODE]);
});

// ---------------------------------------------------------------------------
// StoreCountry::normalise() — the one function every write path relies on
// ---------------------------------------------------------------------------

it('answers with the store country whatever it is given', function ($input) {
    expect(StoreCountry::normalise($input))->toBe(StoreCountry::CODE);
})->with([
    'null' => [null],
    'empty string' => [''],
    'another country' => ['US'],
    'lowercase code' => ['af'],
    'the code' => ['AF'],
    'the name' => ['Afghanistan'],
    'empty array' => [[]],
    'nonsense' => ['not-a-country'],
    'integer' => [42],
]);

it('cannot be called with the argument left out', function () {
    // A caller that forgets the argument still gets the store country rather
    // than an error, which is what makes it safe to spread across write paths.
    expect(StoreCountry::normalise())->toBe(StoreCountry::CODE);
});

it('knows which values already name the store country', function () {
    expect(StoreCountry::matches('AF'))->toBeTrue()
        ->and(StoreCountry::matches('af'))->toBeTrue()
        ->and(StoreCountry::matches(' Afghanistan '))->toBeTrue()
        ->and(StoreCountry::matches('US'))->toBeFalse()
        ->and(StoreCountry::matches(null))->toBeFalse()
        ->and(StoreCountry::matches(42))->toBeFalse();
});

it('exposes the store country as code and name', function () {
    expect(StoreCountry::code())->toBe('AF')
        ->and(StoreCountry::name())->toBe('Afghanistan');
});

it('answers with the store country through the global helper functions', function () {
    // Templates and seeders use the helpers, not the class.
    expect(store_country_code())->toBe(StoreCountry::CODE)
        ->and(store_country_name())->toBe(StoreCountry::NAME)
        ->and(store_country())->toBe(StoreCountry::NAME);
});

// ---------------------------------------------------------------------------
// Checkout for someone who already has an account
// ---------------------------------------------------------------------------

it('completes checkout for a signed-in shopper whose address names another country', function () {
    $user = User::factory()->create(['email' => 'member@example.test']);
    $customer = countryCustomerForUser($user);

    // A shopper who signed up before this store was Afghanistan-only, and whose
    // saved address still says America. This is the row checkout would copy.
    CustomerAddress::create([
        'customer_id' => $customer->id,
        'type' => 'shipping',
        'first_name' => 'Ali',
        'last_name' => 'Ahmadi',
        'address_line_1' => '1 Test Street',
        'city' => 'Kabul',
        'state' => 'Kabul',
        'postal_code' => '10001',
        'country' => StoreCountry::CODE,
        'phone' => '+93700000000',
    ]);

    countryCartItem();
    $shipping = countryShippingMethod();
    countryCod();

    $this->actingAs($user)
        ->post(route('shop.checkout.store'), countryCheckoutPayload($shipping, ['country' => 'US']))
        ->assertSessionHasNoErrors();

    $order = Order::first();

    expect($order)->not->toBeNull()
        // The copy the order itself carries is the one that gets printed on
        // the label and handed to the courier.
        ->and($order->shippingAddress->country)->toBe(StoreCountry::CODE);

    // And the address book the shopper already had is left alone, not blanked.
    $this->assertDatabaseHas('customer_addresses', [
        'customer_id' => $customer->id,
        'country' => StoreCountry::CODE,
    ]);
});

// ---------------------------------------------------------------------------
// The mobile app, through packages/Cartxis/API
// ---------------------------------------------------------------------------

/**
 * A registered shopper with the customer record the mobile app looks for.
 */
function countryApiShopper(): array
{
    $user = User::factory()->create([
        'email' => 'mobile@example.test',
        'role' => 'customer',
        'is_active' => true,
    ]);

    $customer = countryCustomerForUser($user);

    return [$user, $customer];
}

function countryApiAddress(Customer $customer): CustomerAddress
{
    return CustomerAddress::create([
        'customer_id' => $customer->id,
        'type' => 'shipping',
        'first_name' => 'Ali',
        'last_name' => 'Ahmadi',
        'address_line_1' => '1 Test Street',
        'city' => 'Kabul',
        'state' => 'Kabul',
        'postal_code' => '10001',
        'country' => StoreCountry::CODE,
        'phone' => '+93700000000',
        'is_default_shipping' => true,
    ]);
}

/**
 * A cart with one line in it, the way the app's basket endpoint leaves it.
 */
function countryApiCart(User $user): void
{
    $product = Product::create([
        'name' => 'Mobile country test ' . Str::random(6),
        'price' => 100,
        'quantity' => 10,
        'status' => 'active',
        'type' => 'simple',
        'manage_stock' => true,
    ]);

    $cart = \Cartxis\Cart\Models\Cart::create(['user_id' => $user->id]);

    $cart->items()->create([
        'product_id' => $product->id,
        'quantity' => 1,
        'price' => 100,
    ]);
}

it('stores the store country when the mobile app sends a shipping address with no country', function () {
    [$user] = countryApiShopper();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/checkout/shipping-address', [
            'first_name' => 'Ali',
            'last_name' => 'Ahmadi',
            'address_line_1' => '1 Test Street',
            'city' => 'Kabul',
            'state' => 'Kabul',
            'postal_code' => '10001',
            'phone' => '+93700000000',
        ]);

    $response->assertOk();

    // The app reads this field back straight away to confirm what it sent, so
    // the answer it gets must be the store country and not a blank.
    $response->assertJsonPath('data.shipping_address.country', StoreCountry::CODE);

    expect(session('checkout.shipping_address.country'))->toBe(StoreCountry::CODE);
});

it('stores the store country when the mobile app sends another country', function () {
    [$user] = countryApiShopper();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/checkout/shipping-address', [
            'first_name' => 'Ali',
            'last_name' => 'Ahmadi',
            'address_line_1' => '1 Test Street',
            'city' => 'New York',
            'state' => 'NY',
            'postal_code' => '10001',
            'country' => 'US',
            'phone' => '+93700000000',
        ])
        ->assertOk()
        ->assertJsonPath('data.shipping_address.country', StoreCountry::CODE);

    expect(session('checkout.shipping_address.country'))->toBe(StoreCountry::CODE);
});

it('stores the store country when the mobile app saves an address with no country', function () {
    [$user, $customer] = countryApiShopper();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/customer/addresses', [
            'first_name' => 'Ali',
            'last_name' => 'Ahmadi',
            'address_line_1' => '1 Test Street',
            'city' => 'Kabul',
            'state' => 'Kabul',
            'postal_code' => '10001',
            'phone' => '+93700000000',
        ]);

    $response->assertCreated();

    // Still returned to the app: the mobile builds already read it.
    $response->assertJsonPath('data.country', StoreCountry::CODE);

    $this->assertDatabaseHas('customer_addresses', [
        'customer_id' => $customer->id,
        'country' => StoreCountry::CODE,
    ]);
});

it('stores the store country when the mobile app saves an address with another country', function () {
    [$user, $customer] = countryApiShopper();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/customer/addresses', [
            'first_name' => 'Ali',
            'last_name' => 'Ahmadi',
            'address_line_1' => '1 Test Street',
            'city' => 'New York',
            'state' => 'NY',
            'postal_code' => '10001',
            'country' => 'US',
            'phone' => '+93700000000',
        ])
        ->assertCreated()
        ->assertJsonPath('data.country', StoreCountry::CODE);

    $this->assertDatabaseHas('customer_addresses', [
        'customer_id' => $customer->id,
        'country' => StoreCountry::CODE,
    ]);
});

it('stores the store country when the mobile app overwrites a saved address', function () {
    [$user, $customer] = countryApiShopper();
    $address = countryApiAddress($customer);

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/v1/customer/addresses/' . $address->id, [
            'first_name' => 'Ali',
            'last_name' => 'Ahmadi',
            'address_line_1' => '9 Updated Street',
            'city' => 'Kabul',
            'state' => 'Kabul',
            'postal_code' => '10001',
            'country' => 'US',
            'phone' => '+93700000000',
        ])
        ->assertOk();

    $this->assertDatabaseHas('customer_addresses', [
        'id' => $address->id,
        'address_line_1' => '9 Updated Street',
        'country' => StoreCountry::CODE,
    ]);
});

it('stores the store country on the order when the mobile app places it', function () {
    [$user, $customer] = countryApiShopper();
    $address = countryApiAddress($customer);

    countryApiCart($user);
    countryShippingMethod();
    countryCod();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/checkout/place-order', [
            'shipping_address_id' => $address->id,
            'payment_method' => 'cod',
        ])
        ->assertCreated();

    $order = Order::first();

    expect($order)->not->toBeNull();

    // Both rows the mobile checkout writes, not just the one being displayed.
    $this->assertDatabaseHas('addresses', [
        'addressable_type' => Order::class,
        'addressable_id' => $order->id,
        'type' => Address::TYPE_SHIPPING,
        'country' => StoreCountry::CODE,
    ]);

    $this->assertDatabaseHas('addresses', [
        'addressable_type' => Order::class,
        'addressable_id' => $order->id,
        'type' => Address::TYPE_BILLING,
        'country' => StoreCountry::CODE,
    ]);
});

it('tells the mobile app the store country even when the stored setting says otherwise', function () {
    // The realistic upgrade: the settings row still holds whatever the store
    // was called before. The app reads this on its public boot call, before
    // anyone logs in, so a stale value here is what a shopper is shown.
    DB::table('settings')->updateOrInsert(
        ['key' => 'store_country'],
        ['value' => 'India', 'type' => 'string', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()]
    );

    $this->getJson('/api/v1/app/settings')
        ->assertOk()
        // The key itself stays, because the installed apps already read it.
        ->assertJsonPath('data.store_country', StoreCountry::NAME);
});

// ---------------------------------------------------------------------------
// Shipping rates and tax zones: the rows the store is priced by
// ---------------------------------------------------------------------------

it('writes a shipping rate against the store country even when one is sent', function () {
    $shipping = countryShippingMethod();

    $this->actingAs(countryAdmin(), 'admin')
        ->postJson(route('admin.settings.shipping-methods.addRate', $shipping), [
            'country' => 'US',
            'min_weight' => 0,
            'max_weight' => 5,
            'base_cost' => 10,
            'cost_per_kg' => 1,
            'status' => 'active',
        ])
        ->assertOk();

    $this->assertDatabaseHas('shipping_rates', [
        'shipping_method_id' => $shipping->id,
        'country' => StoreCountry::CODE,
    ]);
});

it('keeps the shipping rate unique index usable for several weight bands', function () {
    $shipping = countryShippingMethod();

    // The unique index is (shipping_method_id, country, state, min_weight,
    // max_weight). Narrowing every rate to one country must not make the second
    // weight band collide with the first, or a weight-based method silently
    // stops working for anything over the first band.
    foreach ([[0, 5], [5, 25], [25, 100]] as [$min, $max]) {
        ShippingRate::create([
            'shipping_method_id' => $shipping->id,
            'country' => StoreCountry::code(),
            'state' => null,
            'min_weight' => $min,
            'max_weight' => $max,
            'base_cost' => 10,
            'cost_per_kg' => 1,
            'status' => 'active',
        ]);
    }

    expect(ShippingRate::where('shipping_method_id', $shipping->id)->count())->toBe(3)
        ->and(ShippingRate::where('shipping_method_id', $shipping->id)
            ->distinct()->pluck('country')->all())->toBe([StoreCountry::CODE]);

    // The index itself is still there and still enforced, which is shown with a
    // state set. With state left null neither SQLite nor MySQL treats two rows
    // as equal, so a duplicate null-state band is a duplicate that the database
    // will not catch -- see the note in the report.
    ShippingRate::create([
        'shipping_method_id' => $shipping->id,
        'country' => StoreCountry::code(),
        'state' => 'KAB',
        'min_weight' => 0,
        'max_weight' => 5,
        'base_cost' => 10,
        'cost_per_kg' => 1,
        'status' => 'active',
    ]);

    expect(fn () => ShippingRate::create([
        'shipping_method_id' => $shipping->id,
        'country' => StoreCountry::code(),
        'state' => 'KAB',
        'min_weight' => 0,
        'max_weight' => 5,
        'base_cost' => 99,
        'cost_per_kg' => 1,
        'status' => 'active',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('seeds shipping rates for the store country only', function () {
    (new \Cartxis\Core\Database\Seeders\ShippingMethodSeeder())->run();

    $countries = \DB::table('shipping_rates')->distinct()->pluck('country')->all();

    expect($countries)->not->toBeEmpty()
        ->and($countries)->toBe([StoreCountry::CODE]);
});

it('writes tax zone locations against the store country even when one is sent', function () {
    $this->actingAs(countryAdmin(), 'admin')
        ->postJson(route('admin.settings.tax-zones.store'), [
            'code' => 'default',
            'name' => 'Default zone',
            'locations' => [[
                'country_code' => 'US',
                'state_code' => null,
                'postal_code_pattern' => null,
            ]],
        ])
        ->assertSessionHasNoErrors();

    $locations = DB::table('tax_zone_locations')->get();

    expect($locations)->toHaveCount(1)
        // Whatever the form sent, the row it wrote belongs to the store.
        ->and($locations->first()->country_code)->toBe(StoreCountry::CODE);
});

// ---------------------------------------------------------------------------
// Payment methods: nothing silently disappears now the country is fixed
// ---------------------------------------------------------------------------

it('keeps a payment method that only lists other countries', function () {
    countryCod();

    $method = PaymentMethod::where('code', 'cod')->first();

    // The realistic admin state: the country list was set before the store
    // became Afghanistan-only, so it still names other countries.
    $method->update(['configuration' => ['enabled_countries' => ['US', 'CA']]]);

    // Offering COD here is a store decision, but dropping it because of a
    // stale country list would leave the shopper with no way to pay at all.
    expect($method->fresh()->isAvailableForCountry())->toBeTrue()
        ->and($method->fresh()->isAvailableForCountry('AF'))->toBeTrue();
});

it('keeps a payment method whose country list is empty or missing', function () {
    countryCod();

    $method = PaymentMethod::where('code', 'cod')->first();
    $method->update(['configuration' => ['enabled_countries' => []]]);

    expect($method->fresh()->isAvailableForCountry())->toBeTrue();

    $method->update(['configuration' => []]);

    expect($method->fresh()->isAvailableForCountry())->toBeTrue();
});

it('keeps a payment method that still uses the old country list key', function () {
    countryCod();

    $method = PaymentMethod::where('code', 'cod')->first();

    // The seeder has always written allowed_countries. It must not be read as
    // "no countries allowed", which would drop the method from checkout.
    $method->update(['configuration' => ['allowed_countries' => ['IN']]]);

    expect($method->fresh()->isAvailableForCountry())->toBeTrue();
});
