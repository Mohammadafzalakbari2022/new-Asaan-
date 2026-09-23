<?php

use App\Models\User;
use Cartxis\Sales\Models\Delivery;
use Cartxis\Sales\Models\DeliveryEvent;
use Cartxis\Sales\Models\Shipment;
use Cartxis\Shop\Models\Order;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

function makeOrder(): Order
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

function makeShipment(Order $order): Shipment
{
    return Shipment::create([
        'order_id' => $order->id,
        'status' => 'pending',
    ]);
}

function makeAdmin(): User
{
    return User::factory()->withoutTwoFactor()->create([
        'role' => 'admin',
        'is_active' => true,
    ]);
}

function makeDriver(?string $name = null): User
{
    return User::factory()->withoutTwoFactor()->create([
        'role' => 'delivery',
        'is_active' => true,
        'name' => $name ?: 'Test Driver',
    ]);
}

test('delivery menu items are seeded', function () {
    $this->artisan('db:seed', ['--class' => Cartxis\Admin\Database\Seeders\AdminMenuSeeder::class])->assertSuccessful();

    $this->assertDatabaseHas('menu_items', ['key' => 'sales-deliveries']);
    $this->assertDatabaseHas('menu_items', ['key' => 'sales-delivery-staff']);
});

test('guests are redirected for delivery admin pages', function () {
    $this->get('/admin/sales/deliveries')->assertStatus(302);
    $this->get('/admin/sales/delivery-staff')->assertStatus(302);
});

test('admin can create a delivery person', function () {
    makeAdmin();

    $response = $this->actingAs(User::where('role', 'admin')->first(), 'admin')->post('/admin/sales/delivery-staff', [
        'name' => 'Ahmad Driver',
        'email' => 'ahmad@asaan.af',
        'phone' => '0799111222',
        'password' => 'secretpass123',
    ]);

    $response->assertSessionHas('success');

    $this->assertDatabaseHas('users', [
        'email' => 'ahmad@asaan.af',
        'role' => 'delivery',
        'is_active' => 1,
    ]);
});

test('admin can update a delivery person', function () {
    $admin = makeAdmin();
    $driver = makeDriver('Old Name');

    $this->actingAs($admin, 'admin')->put("/admin/sales/delivery-staff/{$driver->id}", [
        'name' => 'New Name',
        'email' => $driver->email,
        'phone' => '0700000001',
        'is_active' => false,
    ])->assertSessionHas('success');

    $driver->refresh();

    expect($driver->name)->toBe('New Name');
    expect($driver->phone)->toBe('0700000001');
    expect($driver->is_active)->toBeFalse();
});

test('admin can reset a delivery person password', function () {
    $admin = makeAdmin();
    $driver = makeDriver();

    $this->actingAs($admin, 'admin')->post("/admin/sales/delivery-staff/{$driver->id}/reset-password", [
        'password' => 'brandnewpass123',
    ])->assertSessionHas('success');

    expect(Hash::check('brandnewpass123', $driver->fresh()->password))->toBeTrue();
});

test('admin can delete a delivery person', function () {
    $admin = makeAdmin();
    $driver = makeDriver();

    $this->actingAs($admin, 'admin')->delete("/admin/sales/delivery-staff/{$driver->id}")->assertSessionHas('success');

    $this->assertDatabaseMissing('users', ['id' => $driver->id]);
});

test('non-delivery users cannot be managed as delivery staff', function () {
    $admin = makeAdmin();
    $customer = User::factory()->withoutTwoFactor()->create(['role' => 'customer']);

    $this->actingAs($admin, 'admin')->put("/admin/sales/delivery-staff/{$customer->id}", [
        'name' => $customer->name,
        'email' => $customer->email,
        'is_active' => true,
    ])->assertStatus(404);
});

test('admin can assign a shipment to a driver', function () {
    $admin = makeAdmin();
    $driver = makeDriver();
    $shipment = makeShipment(makeOrder());

    $response = $this->actingAs($admin, 'admin')->post('/admin/sales/deliveries', [
        'shipment_id' => $shipment->id,
        'assigned_to' => $driver->id,
        'priority' => 'high',
        'cod_amount' => 50,
    ]);

    $this->assertDatabaseHas('deliveries', [
        'shipment_id' => $shipment->id,
        'assigned_to' => $driver->id,
        'assigned_by' => $admin->id,
        'status' => 'assigned',
        'priority' => 'high',
        'cod_amount' => 50,
    ]);

    expect($shipment->fresh()->status)->toBe('shipped');

    $this->assertDatabaseHas('delivery_events', [
        'from_status' => 'pending',
        'to_status' => 'assigned',
        'note' => 'Assigned to delivery driver',
    ]);
});

test('a shipment cannot be assigned twice while a delivery is active', function () {
    $admin = makeAdmin();
    $driver = makeDriver();
    $shipment = makeShipment(makeOrder());

    $this->actingAs($admin, 'admin')->post('/admin/sales/deliveries', [
        'shipment_id' => $shipment->id,
        'assigned_to' => $driver->id,
    ]);

    $this->actingAs($admin, 'admin')->post('/admin/sales/deliveries', [
        'shipment_id' => $shipment->id,
        'assigned_to' => $driver->id,
    ])->assertSessionHas('error', 'This shipment already has an active delivery assignment.');

    expect(Delivery::where('shipment_id', $shipment->id)->count())->toBe(1);
});

test('an inactive user cannot be assigned as a driver', function () {
    $admin = makeAdmin();
    $driver = makeDriver();
    $driver->update(['is_active' => false]);
    $shipment = makeShipment(makeOrder());

    $this->actingAs($admin, 'admin')->post('/admin/sales/deliveries', [
        'shipment_id' => $shipment->id,
        'assigned_to' => $driver->id,
    ])->assertSessionHas('error');

    $this->assertDatabaseMissing('deliveries', ['shipment_id' => $shipment->id]);
});

test('admin can reassign a delivery to another driver', function () {
    $admin = makeAdmin();
    $driverA = makeDriver('Driver A');
    $driverB = makeDriver('Driver B');
    $delivery = Delivery::create([
        'shipment_id' => makeShipment(makeOrder())->id,
        'order_id' => Order::latest('id')->first()->id,
        'assigned_by' => $admin->id,
        'assigned_to' => $driverA->id,
        'status' => 'assigned',
    ]);

    $this->actingAs($admin, 'admin')->put("/admin/sales/deliveries/{$delivery->id}", [
        'assigned_to' => $driverB->id,
    ])->assertSessionHas('success');

    expect($delivery->fresh()->assigned_to)->toBe($driverB->id);

    $this->assertDatabaseHas('delivery_events', [
        'delivery_id' => $delivery->id,
        'note' => 'Reassigned by admin',
    ]);
});

test('admin can cancel a delivery and the shipment is cancelled', function () {
    $admin = makeAdmin();
    $driver = makeDriver();
    $shipment = makeShipment(makeOrder());
    $delivery = Delivery::create([
        'shipment_id' => $shipment->id,
        'order_id' => $shipment->order_id,
        'assigned_by' => $admin->id,
        'assigned_to' => $driver->id,
        'status' => 'assigned',
    ]);

    $this->actingAs($admin, 'admin')->post("/admin/sales/deliveries/{$delivery->id}/cancel", [
        'note' => 'Customer declined the package',
    ])->assertSessionHas('success');

    expect($delivery->fresh()->status)->toBe(Delivery::STATUS_CANCELLED);
    expect($shipment->fresh()->status)->toBe(Shipment::STATUS_CANCELLED);

    $this->assertDatabaseHas('delivery_events', [
        'delivery_id' => $delivery->id,
        'from_status' => 'assigned',
        'to_status' => 'cancelled',
        'note' => 'Customer declined the package',
    ]);
});

test('a finished delivery cannot be reassigned', function () {
    $admin = makeAdmin();
    $driverA = makeDriver('Driver A');
    $driverB = makeDriver('Driver B');
    $shipment = makeShipment(makeOrder());
    $delivery = Delivery::create([
        'shipment_id' => $shipment->id,
        'order_id' => $shipment->order_id,
        'assigned_by' => $admin->id,
        'assigned_to' => $driverA->id,
        'status' => 'delivered',
    ]);

    $this->actingAs($admin, 'admin')->put("/admin/sales/deliveries/{$delivery->id}", [
        'assigned_to' => $driverB->id,
    ])->assertSessionHas('error', 'A finished delivery cannot be reassigned.');

    expect($delivery->fresh()->assigned_to)->toBe($driverA->id);
});

test('delivery staff list shows only delivery role users', function () {
    $admin = makeAdmin();
    makeDriver('Only Driver');
    User::factory()->withoutTwoFactor()->create(['role' => 'customer', 'name' => 'A Customer']);

    $response = $this->actingAs($admin, 'admin')->get('/admin/sales/delivery-staff');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Sales/DeliveryStaff/Index')
        ->has('staff.data', 1)
        ->where('staff.data.0.name', 'Only Driver'));
});

test('delivery events are created with each assignment', function () {
    $admin = makeAdmin();
    $driver = makeDriver();
    $shipment = makeShipment(makeOrder());

    $this->actingAs($admin, 'admin')->post('/admin/sales/deliveries', [
        'shipment_id' => $shipment->id,
        'assigned_to' => $driver->id,
    ]);

    expect(DeliveryEvent::whereHas('delivery', fn ($q) => $q->where('shipment_id', $shipment->id))->count())->toBe(1);
});