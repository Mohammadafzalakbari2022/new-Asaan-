<?php

declare(strict_types=1);

use Cartxis\Service\Http\Controllers\ServiceBookingController;
use Cartxis\Service\Http\Controllers\ServiceCatalogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Services Storefront Routes
|--------------------------------------------------------------------------
|
| The public face of the services catalogue. Loaded by the ServiceServiceProvider
| inside the "web" middleware group.
|
| The fixed segments come first so a service called "Track" or "Booked" cannot
| shadow the tracking and confirmation pages.
|
*/

Route::middleware(['web'])->group(function () {
    Route::get('/services', [ServiceCatalogController::class, 'index'])->name('services.index');

    Route::get('/services/track', [ServiceCatalogController::class, 'track'])->name('services.track');

    Route::get('/services/booked/{reference}', [ServiceBookingController::class, 'booked'])
        ->name('services.booked');

    Route::get('/services/category/{category:slug}', [ServiceCatalogController::class, 'category'])
        ->name('services.category.show');

    Route::get('/services/{service:slug}', [ServiceCatalogController::class, 'show'])
        ->name('services.show');

    // Booking a job. Throttled like the checkout, because it writes an order.
    Route::post('/services/{service:slug}/book', [ServiceBookingController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('services.book.store');
});
