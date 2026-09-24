<?php

use App\Models\User;
use Cartxis\Sales\Models\Delivery;
use Cartxis\Sales\Models\Shipment;
use Cartxis\Shop\Models\Address;
use Cartxis\Shop\Models\Order;
use Illuminate\Support\Str;

function boardOrder(): Order
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

    Address::create([
        'addressable_type' => Order::class,
        'addressable_id' => $order->id,
        'type' => Address::TYPE_SHIPPING,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'address_line1' => 'Street 10',
        'city' => 'Kabul',
        'state' => 'Kabul',
        'postal_code' => '1001',
        'country' => 'AF',
        'latitude' => 34.5253553,
        'longitude' => 69.181237,
    ]);

    return $order;
}

function boardShipment(Order $order): Shipment
{
    return Shipment::create([
        'order_id' => $order->id,
        'status' => 'shipped',
    ]);
}

function boardAdmin(): User
{
    return User::factory()->withoutTwoFactor()->create([
        'role' => 'admin',
        'is_active' => true,
    ]);
}

function boardDriver(string $name = 'Board Driver'): User
{
    return User::factory()->withoutTwoFactor()->create([
        'role' => 'delivery',
        'is_active' => true,
        'name' => $name,
    ]);
}

function boardDelivery(array $attributes = []): Delivery
{
    $defaults = [
        'status' => 'out_for_delivery',
        'priority' => 'normal',
        'customer_phone' => '0700000000',
        'cod_amount' => 50,
        'last_latitude' => 34.5563889,
        'last_longitude' => 69.2013889,
        'last_location_at' => now()->subMinutes(5),
    ];

    return Delivery::create(array_merge($defaults, $attributes));
}

test('delivery board and report menu items are seeded', function () {
    $this->artisan('db:seed', ['--class' => Cartxis\Admin\Database\Seeders\AdminMenuSeeder::class])->assertSuccessful();

    $this->assertDatabaseHas('menu_items', ['key' => 'sales-delivery-board', 'route' => 'admin.sales.deliveries.board']);
    $this->assertDatabaseHas('menu_items', ['key' => 'sales-deliveries']);
    $this->assertDatabaseHas('menu_items', ['key' => 'reports-delivery', 'route' => 'admin.reports.delivery']);
});

test('guests are redirected from the delivery board', function () {
    $this->get('/admin/sales/deliveries/board')->assertStatus(302);
});

test('admin sees active deliveries with driver position and destination on the board', function () {
    $admin = boardAdmin();
    $driver = boardDriver();
    $order = boardOrder();
    $shipment = boardShipment($order);

    boardDelivery([
        'shipment_id' => $shipment->id,
        'order_id' => $order->id,
        'assigned_by' => $admin->id,
        'assigned_to' => $driver->id,
        'status' => Delivery::STATUS_OUT_FOR_DELIVERY,
        'priority' => 'high',
        'last_latitude' => 34.5563889,
        'last_longitude' => 69.2013889,
        'last_location_at' => now()->subMinutes(5),
    ]);

    $response = $this->actingAs($admin, 'admin')->get('/admin/sales/deliveries/board');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Sales/Deliveries/Board')
        ->has('activeDeliveries', 1)
        ->where('activeDeliveries.0.shipment_number', $shipment->shipment_number)
        ->where('activeDeliveries.0.priority', 'high')
        ->where('activeDeliveries.0.destination.lat', 34.5253553)
        ->where('activeDeliveries.0.destination.lng', 69.181237)
        ->where('activeDeliveries.0.driver.name', $driver->name)
        ->where('activeDeliveries.0.driver_location.live', true));
});

test('the board only lists deliveries that are still on the road', function () {
    $admin = boardAdmin();
    $driver = boardDriver();
    $order = boardOrder();
    $shipment = boardShipment($order);

    boardDelivery([
        'shipment_id' => $shipment->id,
        'order_id' => $order->id,
        'assigned_by' => $admin->id,
        'assigned_to' => $driver->id,
        'status' => Delivery::STATUS_DELIVERED,
        'last_location_at' => now()->subHours(3),
    ]);

    $response = $this->actingAs($admin, 'admin')->get('/admin/sales/deliveries/board');

    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Sales/Deliveries/Board')
        ->has('activeDeliveries', 0));
});

test('a stale driver position is marked as not live', function () {
    $admin = boardAdmin();
    $driver = boardDriver();
    $order = boardOrder();
    $shipment = boardShipment($order);

    boardDelivery([
        'shipment_id' => $shipment->id,
        'order_id' => $order->id,
        'assigned_by' => $admin->id,
        'assigned_to' => $driver->id,
        'status' => Delivery::STATUS_ASSIGNED,
        'last_location_at' => now()->subHours(2),
    ]);

    $response = $this->actingAs($admin, 'admin')->get('/admin/sales/deliveries/board');

    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Sales/Deliveries/Board')
        ->where('activeDeliveries.0.driver_location.live', false));
});