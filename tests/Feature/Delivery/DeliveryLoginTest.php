<?php

use App\Models\User;

test('delivery login screen can be rendered', function () {
    $response = $this->get(route('delivery.login'));

    $response->assertStatus(200);
});

test('delivery staff can authenticate via the delivery login', function () {
    $driver = User::factory()->withoutTwoFactor()->create([
        'role' => 'delivery',
        'is_active' => true,
    ]);

    $response = $this->post(route('delivery.login.store'), [
        'email' => $driver->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated('delivery');
    $response->assertRedirect('/delivery');
});

test('delivery staff can not authenticate with an invalid password', function () {
    $driver = User::factory()->withoutTwoFactor()->create([
        'role' => 'delivery',
        'is_active' => true,
    ]);

    $this->post(route('delivery.login.store'), [
        'email' => $driver->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest('delivery');
});

test('admins can not authenticate via the delivery login', function () {
    $admin = User::factory()->withoutTwoFactor()->create([
        'role' => 'admin',
        'is_active' => true,
    ]);

    $this->post(route('delivery.login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $this->assertGuest('delivery');
});

test('customers can not authenticate via the delivery login', function () {
    $customer = User::factory()->withoutTwoFactor()->create([
        'role' => 'customer',
        'is_active' => true,
    ]);

    $this->post(route('delivery.login.store'), [
        'email' => $customer->email,
        'password' => 'password',
    ]);

    $this->assertGuest('delivery');
});

test('deactivated delivery staff can not authenticate', function () {
    $driver = User::factory()->withoutTwoFactor()->create([
        'role' => 'delivery',
        'is_active' => false,
    ]);

    $this->post(route('delivery.login.store'), [
        'email' => $driver->email,
        'password' => 'password',
    ]);

    $this->assertGuest('delivery');
});

test('delivery staff can view their dashboard', function () {
    $driver = User::factory()->withoutTwoFactor()->create([
        'role' => 'delivery',
        'is_active' => true,
    ]);

    $this->actingAs($driver, 'delivery')->get(route('delivery.dashboard'))
        ->assertStatus(200);
});

test('admins can not access the delivery portal', function () {
    $admin = User::factory()->withoutTwoFactor()->create([
        'role' => 'admin',
        'is_active' => true,
    ]);

    $this->actingAs($admin, 'delivery')->get(route('delivery.dashboard'))
        ->assertRedirect(route('delivery.login'));
});

test('delivery staff can not access the admin panel', function () {
    $driver = User::factory()->withoutTwoFactor()->create([
        'role' => 'delivery',
        'is_active' => true,
    ]);

    $this->actingAs($driver, 'admin')->get(route('admin.dashboard'))
        ->assertRedirect(route('admin.login'));
});

test('delivery staff can log out', function () {
    $driver = User::factory()->withoutTwoFactor()->create([
        'role' => 'delivery',
        'is_active' => true,
    ]);

    $this->actingAs($driver, 'delivery')
        ->post(route('delivery.logout'));

    $this->assertGuest('delivery');
});