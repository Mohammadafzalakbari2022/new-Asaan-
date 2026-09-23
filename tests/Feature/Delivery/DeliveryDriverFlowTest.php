<?php

use App\Models\User;
use Cartxis\Sales\Models\Delivery;
use Cartxis\Sales\Models\Shipment;
use Cartxis\Sales\Services\DeliveryStatusService;
use Cartxis\Shop\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Creates the exact same object graph the passing admin suite uses:
 * Order::create, an address row, a pending shipment, an assigned delivery.
 */
function fixtureDriver(DeliveryStatusService $service): array
{
    $driver = User::factory()->withoutTwoFactor()->create([
        'role' => 'delivery',
        'is_active' => true,
    ]);

    $order = Order::create([
        'order_number' => 'ORD-' . Str::upper(Str::random(8)),
        'status' => 'processing',
        'payment_status' => 'pending',
        'customer_email' => 'buyer@example.com',
        'customer_phone' => '07001234567',
        'subtotal' => 1200,
        'total' => 1200,
    ]);

    $order->addresses()->create([
        'first_name' => 'Sana',
        'last_name' => 'Khan',
        'phone' => '07001234567',
        'address_line1' => 'House 21, Street 4',
        'city' => 'Rawalpindi',
        'state' => 'Punjab',
        'postal_code' => '46000',
        'country' => 'Pakistan',
        'is_default' => true,
    ]);

    $shipment = Shipment::create([
        'order_id' => $order->id,
        'status' => 'pending',
    ]);

    $delivery = Delivery::create([
        'shipment_id' => $shipment->id,
        'order_id' => $order->id,
        'assigned_to' => $driver->id,
        'status' => 'assigned',
    ]);

    return [$driver, $delivery, $shipment, $order];
}

test('a driver sees only deliveries assigned to them', function () {
    [$driverA, , , ] = fixtureDriver(app(DeliveryStatusService::class));
    [, , , $otherOrder] = fixtureDriver(app(DeliveryStatusService::class));

    $this->actingAs($driverA, 'delivery')->get('/delivery/deliveries')
        ->assertOk()
        ->assertDontSee($otherOrder->order_number);
});

test('a guest is redirected to the delivery login', function () {
    $this->get('/delivery/deliveries')->assertRedirect('/delivery/login');
});

test('a driver cannot open another driver delivery', function () {
    [$driverA, , , ] = fixtureDriver(app(DeliveryStatusService::class));
    [, $otherDelivery, , ] = fixtureDriver(app(DeliveryStatusService::class));

    $this->actingAs($driverA, 'delivery')
        ->get('/delivery/deliveries/' . $otherDelivery->id)
        ->assertStatus(403);
});

test('a driver can start an assigned delivery', function () {
    [$driver, $delivery, , ] = fixtureDriver(app(DeliveryStatusService::class));

    $this->actingAs($driver, 'delivery')->post(
        '/delivery/deliveries/' . $delivery->id . '/start'
    )->assertRedirect();

    $this->assertDatabaseHas('deliveries', [
        'id' => $delivery->id,
        'status' => Delivery::STATUS_OUT_FOR_DELIVERY,
    ]);
});

test('arriving is blocked until the delivery starts', function () {
    [$driver, $delivery, , ] = fixtureDriver(app(DeliveryStatusService::class));

    $this->actingAs($driver, 'delivery')->post(
        '/delivery/deliveries/' . $delivery->id . '/arriving'
    )->assertSessionHas('error');
});

test('a driver can share live location during out-for-delivery', function () {
    [$driver, $delivery, , ] = fixtureDriver(app(DeliveryStatusService::class));

    app(DeliveryStatusService::class)->transition(
        $delivery->fresh(), Delivery::STATUS_OUT_FOR_DELIVERY
    );

    $this->actingAs($driver, 'delivery')->post(
        '/delivery/deliveries/' . $delivery->id . '/location', [
            'latitude' => 33.6363,
            'longitude' => 73.0999,
            'accuracy' => 12,
        ]
    )->assertOk();

    $this->assertDatabaseHas('deliveries', [
        'id' => $delivery->id,
        'last_latitude' => 33.6363,
        'last_longitude' => 73.0999,
        'last_location_at' => $delivery->fresh()->last_location_at,
    ]);
});

test('a driver can mark a delivery as arriving once started', function () {
    [$driver, $delivery, , ] = fixtureDriver(app(DeliveryStatusService::class));

    app(DeliveryStatusService::class)->transition(
        $delivery->fresh(), Delivery::STATUS_OUT_FOR_DELIVERY
    );

    $this->actingAs($driver, 'delivery')->post(
        '/delivery/deliveries/' . $delivery->id . '/arriving'
    )->assertRedirect();

    $this->assertDatabaseHas('deliveries', [
        'id' => $delivery->id,
        'status' => Delivery::STATUS_ARRIVING,
    ]);
});

test('a driver can deliver a parcel and the order completes', function () {
    [$driver, $delivery, $shipment, $order] = fixtureDriver(app(DeliveryStatusService::class));

    app(DeliveryStatusService::class)->transition(
        $delivery->fresh(), Delivery::STATUS_OUT_FOR_DELIVERY
    );

    $this->actingAs($driver, 'delivery')->post(
        '/delivery/deliveries/' . $delivery->id . '/deliver', [
            'recipient_name' => 'Sara Raza',
            'cod_received' => 1100.50,
        ]
    )->assertRedirect();

    $this->assertDatabaseHas('deliveries', [
        'id' => $delivery->id,
        'status' => Delivery::STATUS_DELIVERED,
    ]);
    $this->assertDatabaseHas('shipments', [
        'id' => $shipment->id,
        'status' => Shipment::STATUS_DELIVERED,
    ]);
    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => Order::STATUS_COMPLETED,
        'payment_status' => Order::PAYMENT_PENDING,
    ]);
});

test('a driver can mark a delivery undelivered', function () {
    [$driver, $delivery, $shipment, $order] = fixtureDriver(app(DeliveryStatusService::class));

    app(DeliveryStatusService::class)->transition(
        $delivery->fresh(), Delivery::STATUS_OUT_FOR_DELIVERY
    );

    $this->actingAs($driver, 'delivery')->post(
        '/delivery/deliveries/' . $delivery->id . '/undelivered', [
            'failure_reason' => 'customer_unavailable',
            'failure_note' => 'No one at the address',
        ]
    )->assertRedirect();

    $this->assertDatabaseHas('deliveries', [
        'id' => $delivery->id,
        'status' => Delivery::STATUS_UNDELIVERED,
    ]);
    $this->assertDatabaseHas('shipments', [
        'id' => $shipment->id,
        'status' => Shipment::STATUS_FAILED,
    ]);
    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => Order::STATUS_PROCESSING,
    ]);
});

test('a finished delivery cannot be delivered twice', function () {
    [$driver, $delivery, , ] = fixtureDriver(app(DeliveryStatusService::class));

    app(DeliveryStatusService::class)->transition(
        $delivery->fresh(), Delivery::STATUS_OUT_FOR_DELIVERY
    );
    app(DeliveryStatusService::class)->transition(
        $delivery->fresh(), Delivery::STATUS_DELIVERED
    );

    $this->actingAs($driver, 'delivery')->post(
        '/delivery/deliveries/' . $delivery->id . '/deliver', [
            'recipient_name' => 'Anyone',
        ]
    )->assertRedirect();
});

