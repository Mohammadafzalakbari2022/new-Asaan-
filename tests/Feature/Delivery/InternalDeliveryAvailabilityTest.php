<?php

use App\Models\User;
use Cartxis\Core\Services\SettingService;
use Cartxis\Sales\Models\Shipment;
use Cartxis\Shop\Models\Order;
use Cartxis\Shop\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Built-in delivery is always available; external couriers are switched off
|--------------------------------------------------------------------------
|
| Two unrelated features were both called "delivery". The in-house one needs
| no API token, no third-party account and no courier setting at all. These
| tests pin that down so the collision cannot come back: the in-house option
| must always show, and the external couriers (Delhivery/Shiprocket) are
| switched off entirely, so they must never show or be accepted.
|
*/

function internalDeliveryOrder(): Order
{
    $order = Order::create([
        'order_number' => 'ORD-' . Str::upper(Str::random(8)),
        'status' => 'processing',
        'payment_status' => 'paid',
        'customer_email' => 'customer@example.com',
        'customer_phone' => '0700000000',
        'subtotal' => 100,
        'total' => 100,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => null,
        'product_name' => 'Test Item',
        'quantity' => 1,
        'price' => 100,
        'total' => 100,
    ]);

    return $order;
}

function internalDeliveryAdmin(): User
{
    return User::factory()->withoutTwoFactor()->create([
        'role' => 'admin',
        'is_active' => true,
    ]);
}

test('the in-house delivery option is available with no courier settings at all', function () {
    $admin = internalDeliveryAdmin();
    $order = internalDeliveryOrder();

    // Nothing about the external courier has been configured anywhere.
    expect(DB::table('settings')->where('key', 'like', 'shipping.courier.%')->count())->toBe(0);
    expect(DB::table('settings')->where('key', 'like', 'shipping.delivery.%')->count())->toBe(0);

    $response = $this->actingAs($admin, 'admin')
        ->get('/admin/sales/shipments/create?order_id=' . $order->id);

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Sales/Shipments/Create')
        ->where('internal_delivery_available', true)
        ->where('courier_available', false));
});

test('creating an in-house delivery shipment sends nothing to any external service', function () {
    Http::fake();

    $admin = internalDeliveryAdmin();
    $order = internalDeliveryOrder();
    $item = $order->items()->first();

    $response = $this->actingAs($admin, 'admin')->post('/admin/sales/shipments', [
        'order_id' => $order->id,
        'shipment_mode' => 'internal_delivery',
        'items' => [
            ['order_item_id' => $item->id, 'quantity' => 1],
        ],
    ]);

    $shipment = Shipment::where('order_id', $order->id)->first();

    expect($shipment)->not->toBeNull();
    expect($shipment->carrier)->toBe('In-house delivery');

    // The whole point: no courier, no token, no outbound HTTP call.
    Http::assertNothingSent();
});

test('the courier key rename carries existing configuration across', function () {
    $now = now();

    DB::table('settings')->insert([
        ['key' => 'shipping.delivery.enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'shipping', 'is_public' => 0, 'created_at' => $now, 'updated_at' => $now],
        ['key' => 'shipping.delivery.api_token', 'value' => 'legacy-token', 'type' => 'string', 'group' => 'shipping', 'is_public' => 0, 'created_at' => $now, 'updated_at' => $now],
        ['key' => 'shipping.delivery.channel_id', 'value' => 'chan-9', 'type' => 'string', 'group' => 'shipping', 'is_public' => 0, 'created_at' => $now, 'updated_at' => $now],
    ]);

    $migration = require base_path('packages/Cartxis/Sales/src/Database/Migrations/2026_10_01_000001_copy_delivery_settings_to_courier.php');
    $migration->up();

    expect(DB::table('settings')->where('key', 'shipping.courier.enabled')->value('value'))->toBe('1');
    expect(DB::table('settings')->where('key', 'shipping.courier.api_token')->value('value'))->toBe('legacy-token');
    expect(DB::table('settings')->where('key', 'shipping.courier.channel_id')->value('value'))->toBe('chan-9');
    expect(DB::table('settings')->where('key', 'like', 'shipping.delivery.%')->count())->toBe(0);
});

test('the external courier option stays hidden even when it is enabled and configured', function () {
    $admin = internalDeliveryAdmin();
    $order = internalDeliveryOrder();
    $settings = app(SettingService::class);

    // Even switched on with a token, the external couriers stay switched off
    // for this store: in-house delivery is the only option.
    $settings->set('shipping.courier.enabled', true, 'boolean', 'shipping');
    $settings->set('shipping.courier.api_token', 'a-courier-token', 'string', 'shipping');

    $response = $this->actingAs($admin, 'admin')
        ->get('/admin/sales/shipments/create?order_id=' . $order->id);

    $response->assertInertia(fn ($page) => $page
        ->where('internal_delivery_available', true)
        ->where('courier_available', false)
        ->where('shiprocket_available', false));
});

test('an external courier shipment is refused', function () {
    Http::fake();

    $admin = internalDeliveryAdmin();
    $order = internalDeliveryOrder();
    $item = $order->items()->first();

    $this->actingAs($admin, 'admin')
        ->post('/admin/sales/shipments', [
            'order_id' => $order->id,
            'shipment_mode' => 'courier',
            'items' => [
                ['order_item_id' => $item->id, 'quantity' => 1],
            ],
        ])
        ->assertSessionHasErrors('shipment_mode');

    expect(Shipment::where('order_id', $order->id)->count())->toBe(0);
    Http::assertNothingSent();
});

test('a shiprocket shipment is refused', function () {
    Http::fake();

    $admin = internalDeliveryAdmin();
    $order = internalDeliveryOrder();
    $item = $order->items()->first();

    $this->actingAs($admin, 'admin')
        ->post('/admin/sales/shipments', [
            'order_id' => $order->id,
            'shipment_mode' => 'shiprocket',
            'items' => [
                ['order_item_id' => $item->id, 'quantity' => 1],
            ],
        ])
        ->assertSessionHasErrors('shipment_mode');

    expect(Shipment::where('order_id', $order->id)->count())->toBe(0);
    Http::assertNothingSent();
});
