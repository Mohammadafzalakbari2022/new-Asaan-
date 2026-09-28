<?php

declare(strict_types=1);

use Cartxis\Service\Http\Controllers\Admin\ServiceBookingController;
use Cartxis\Service\Http\Controllers\Admin\ServiceCategoryController;
use Cartxis\Service\Http\Controllers\Admin\ServiceController;
use Cartxis\Service\Http\Controllers\Admin\ServiceSettingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Service Admin Routes
|--------------------------------------------------------------------------
|
| The services catalogue and the jobs booked from it. Parented under Catalog
| in the sidebar, beside Products, Categories and Brands, because a service is
| something the store owner sells.
|
| The sidebar itself is database-driven, so the menu rows added by the services
| menu migration are the whole of the navigation work here.
|
*/

Route::middleware(['web', 'auth:admin'])
    ->prefix('admin/services')
    ->name('admin.services.')
    ->group(function () {

        // Jobs booked from the website.
        Route::prefix('bookings')->name('bookings.')->group(function () {
            Route::get('/', [ServiceBookingController::class, 'index'])->name('index');
            Route::get('/export/csv', [ServiceBookingController::class, 'export'])->name('export');
            Route::get('/{booking}', [ServiceBookingController::class, 'show'])->name('show');

            Route::post('/{booking}/assign', [ServiceBookingController::class, 'assign'])->name('assign');
            Route::post('/{booking}/unassign', [ServiceBookingController::class, 'unassign'])->name('unassign');
            Route::post('/{booking}/start', [ServiceBookingController::class, 'start'])->name('start');
            Route::post('/{booking}/complete', [ServiceBookingController::class, 'complete'])->name('complete');
            Route::post('/{booking}/cancel', [ServiceBookingController::class, 'cancel'])->name('cancel');
            Route::post('/{booking}/note', [ServiceBookingController::class, 'note'])->name('note');
            Route::post('/bulk-status', [ServiceBookingController::class, 'bulkStatus'])->name('bulk-status');
        });

        // Booking rules: lead time, time slots, coverage, contact details.
        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('/', [ServiceSettingController::class, 'edit'])->name('edit');
            Route::put('/', [ServiceSettingController::class, 'update'])->name('update');
        });

        // Groups of services, e.g. Cleaning, Plumbing, House Shifting.
        Route::prefix('categories')->name('categories.')->group(function () {
            Route::get('/', [ServiceCategoryController::class, 'index'])->name('index');
            Route::get('/create', [ServiceCategoryController::class, 'create'])->name('create');
            Route::post('/', [ServiceCategoryController::class, 'store'])->name('store');
            Route::get('/{category}/edit', [ServiceCategoryController::class, 'edit'])->name('edit');
            Route::put('/{category}', [ServiceCategoryController::class, 'update'])->name('update');
            Route::delete('/{category}', [ServiceCategoryController::class, 'destroy'])->name('destroy');
            Route::post('/bulk-status', [ServiceCategoryController::class, 'bulkStatus'])->name('bulk-status');
        });

        // The services themselves.
        Route::get('/', [ServiceController::class, 'index'])->name('index');
        Route::get('/create', [ServiceController::class, 'create'])->name('create');
        Route::post('/', [ServiceController::class, 'store'])->name('store');
        Route::get('/{service}/edit', [ServiceController::class, 'edit'])->name('edit');
        Route::put('/{service}', [ServiceController::class, 'update'])->name('update');
        Route::delete('/{service}', [ServiceController::class, 'destroy'])->name('destroy');
        Route::post('/bulk-status', [ServiceController::class, 'bulkStatus'])->name('bulk-status');
        Route::post('/check-slug', [ServiceController::class, 'checkSlug'])->name('check-slug');
    });
