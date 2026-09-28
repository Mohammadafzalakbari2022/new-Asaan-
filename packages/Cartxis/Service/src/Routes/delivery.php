<?php

declare(strict_types=1);

use Cartxis\Service\Http\Controllers\Delivery\ServiceJobController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Service Jobs — inside the existing delivery portal
|--------------------------------------------------------------------------
|
| These routes live beside /delivery/deliveries and use the same guard, the same
| session and the same role check. A worker who can see a parcel can see a job.
| There is no second login and no second app.
|
| The "delivery.access" alias is the EnsureDeliveryRole middleware registered in
| bootstrap/app.php, so nothing new is needed to make these staff-only.
|
*/

Route::middleware(['web', 'auth:delivery', 'delivery.access'])->group(function () {
    Route::get('/delivery/jobs', [ServiceJobController::class, 'index'])->name('delivery.jobs.index');

    Route::get('/delivery/jobs/{booking:reference}', [ServiceJobController::class, 'show'])
        ->name('delivery.jobs.show');

    Route::post('/delivery/jobs/{booking:reference}/start', [ServiceJobController::class, 'start'])
        ->name('delivery.jobs.start');

    Route::post('/delivery/jobs/{booking:reference}/complete', [ServiceJobController::class, 'complete'])
        ->name('delivery.jobs.complete');

    Route::post('/delivery/jobs/{booking:reference}/cancel', [ServiceJobController::class, 'cancel'])
        ->name('delivery.jobs.cancel');
});
