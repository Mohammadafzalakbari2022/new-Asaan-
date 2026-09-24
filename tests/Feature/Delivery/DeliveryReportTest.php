<?php

use App\Models\User;
use Cartxis\Sales\Models\Delivery;
use Cartxis\Sales\Models\Shipment;
use Cartxis\Shop\Models\Order;
use Illuminate\Support\Str;

function reportOrder(): Order
{
    return Order::create([
        'order_number' => 'ORD-' . Str::upper(Str::random(8)),
        'status' => 'processing',
        'payment_status' => 'paid',
        'customer_email' => 'customer@example.com',
        'customer_phone' => '0700000000',
        'subtotal' => 100,
        'total' => 100,
    ]);
}

function reportShipment(Order $order): Shipment
{
    return Shipment::create([
        'order_id' => $order->id,
        'status' => 'shipped',
    ]);
}

function reportAdmin(): User
{
    return User::factory()->withoutTwoFactor()->create([
        'role' => 'admin',
        'is_active' => true,
    ]);
}

function reportDriver(string $name = 'Report Driver'): User
{
    return User::factory()->withoutTwoFactor()->create([
        'role' => 'delivery',
        'is_active' => true,
        'name' => $name,
    ]);
}

function reportDelivery(array $attributes = []): Delivery
{
    $defaults = [
        'status' => 'delivered',
        'priority' => 'normal',
        'customer_phone' => '0700000000',
        'cod_amount' => 0,
        'cod_received' => 0,
    ];

    return Delivery::create(array_merge($defaults, $attributes));
}

test('guests are redirected from the delivery report pages', function () {
    $this->get('/admin/reports/delivery')->assertStatus(302);
    $this->get('/admin/reports/delivery/export')->assertStatus(302);
});

test('the delivery report shows statistics for the filtered period', function () {
    $admin = reportAdmin();
    $driver = reportDriver();

    foreach ([60, 40] as $i => $amount) {
        $order = reportOrder();
        $shipment = reportShipment($order);
        reportDelivery([
            'shipment_id' => $shipment->id,
            'order_id' => $order->id,
            'assigned_by' => $admin->id,
            'assigned_to' => $driver->id,
            'status' => Delivery::STATUS_DELIVERED,
            'cod_amount' => $amount,
            'cod_received' => $amount,
            'customer_phone' => "070000000{$i}",
        ]);
    }

    $undeliveredOrder = reportOrder();
    reportDelivery([
        'shipment_id' => reportShipment($undeliveredOrder)->id,
        'order_id' => $undeliveredOrder->id,
        'assigned_by' => $admin->id,
        'assigned_to' => $driver->id,
        'status' => Delivery::STATUS_UNDELIVERED,
        'cod_amount' => 30,
    ]);

    $response = $this->actingAs($admin, 'admin')->get('/admin/reports/delivery');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Reports/Delivery/Index')
        ->where('statistics.total', 3)
        ->where('statistics.delivered', 2)
        ->where('statistics.undelivered', 1)
        ->where('statistics.delivery_rate', 66.7)
        ->where('statistics.cod_expected', 130)
        ->where('statistics.cod_collected', 100)
        ->has('perDriver', 1)
        ->where('perDriver.0.driver.name', $driver->name)
        ->where('perDriver.0.total', 3)
        ->where('perDriver.0.delivered', 2)
        ->has('deliveries.data', 3)
        ->has('drivers', 1));
});

test('the report filters by driver and status', function () {
    $admin = reportAdmin();
    $driverA = reportDriver('Driver A');
    $driverB = reportDriver('Driver B');

    $orderA = reportOrder();
    reportDelivery([
        'shipment_id' => reportShipment($orderA)->id,
        'order_id' => $orderA->id,
        'assigned_by' => $admin->id,
        'assigned_to' => $driverA->id,
        'status' => Delivery::STATUS_DELIVERED,
        'cod_amount' => 50,
        'cod_received' => 50,
    ]);

    $orderB = reportOrder();
    reportDelivery([
        'shipment_id' => reportShipment($orderB)->id,
        'order_id' => $orderB->id,
        'assigned_by' => $admin->id,
        'assigned_to' => $driverB->id,
        'status' => Delivery::STATUS_OUT_FOR_DELIVERY,
    ]);

    $response = $this->actingAs($admin, 'admin')->get('/admin/reports/delivery?driver_id=' . $driverA->id);

    $response->assertInertia(fn ($page) => $page
        ->where('statistics.total', 1)
        ->where('statistics.delivered', 1));

    $response = $this->actingAs($admin, 'admin')->get('/admin/reports/delivery?status=' . Delivery::STATUS_DELIVERED);

    $response->assertInertia(fn ($page) => $page
        ->where('statistics.total', 1)
        ->where('statistics.delivered', 1));
});

test('the delivery report exports a CSV of the filtered deliveries', function () {
    $admin = reportAdmin();
    $driver = reportDriver('Export Driver');
    $order = reportOrder();
    $delivery = reportDelivery([
        'shipment_id' => reportShipment($order)->id,
        'order_id' => $order->id,
        'assigned_by' => $admin->id,
        'assigned_to' => $driver->id,
        'status' => Delivery::STATUS_DELIVERED,
        'cod_amount' => 75,
        'cod_received' => 75,
        'recipient_name' => 'John Doe',
    ]);

    $response = $this->actingAs($admin, 'admin')->get('/admin/reports/delivery/export');

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');

    $content = $response->streamedContent();

    $this->assertStringContainsString('Delivery ID', $content);
    $this->assertStringContainsString('Customer Phone', $content);
    $this->assertStringContainsString('COD Collected', $content);
    $this->assertStringContainsString((string) $delivery->id, $content);
    $this->assertStringContainsString('Export Driver', $content);
    $this->assertStringContainsString('John Doe', $content);
    $this->assertStringContainsString('75', $content);
});