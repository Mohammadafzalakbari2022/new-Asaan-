<?php

use Cartxis\Sales\Http\Controllers\Delivery\DashboardController;
use Cartxis\Sales\Http\Controllers\Delivery\DeliveryLoginController;
use Cartxis\Sales\Http\Middleware\EnsureDeliveryRole;
use Cartxis\Sales\Http\Middleware\RedirectIfDeliveryAuthenticated;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Delivery Staff Portal Routes
|--------------------------------------------------------------------------
|
| Mobile-first area for in-house delivery staff. Uses the dedicated
| 'delivery' guard and its own session cookie (see SetAdminSessionCookie),
| so a driver session never collides with admin or storefront sessions.
|
*/

// Guests only
Route::middleware(['web', RedirectIfDeliveryAuthenticated::class])->group(function () {
    Route::get('/delivery/login', [DeliveryLoginController::class, 'create'])
        ->name('delivery.login');

    Route::post('/delivery/login', [DeliveryLoginController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('delivery.login.store');
});

// Authenticated delivery staff
Route::middleware(['web', 'auth:delivery', 'delivery.access'])->group(function () {
    Route::post('/delivery/logout', [DeliveryLoginController::class, 'destroy'])
        ->name('delivery.logout');

    Route::get('/delivery', [DashboardController::class, 'index'])
        ->name('delivery.dashboard');
});