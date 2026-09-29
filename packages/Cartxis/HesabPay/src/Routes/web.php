<?php

use Illuminate\Support\Facades\Route;
use Cartxis\HesabPay\Http\Controllers\HesabPayController;

/*
|--------------------------------------------------------------------------
| HesabPay Return Routes
|--------------------------------------------------------------------------
|
| Where the customer's browser lands after hosted checkout. These are a
| customer courtesy only. They never mark an order paid, because browser
| navigation is not guaranteed and proves nothing about the payment.
|
*/

Route::middleware(['web'])->group(function () {
    Route::get('hesabpay/return/success/{order}', [HesabPayController::class, 'success'])
        ->name('hesabpay.return.success');

    Route::get('hesabpay/return/failure/{order}', [HesabPayController::class, 'failure'])
        ->name('hesabpay.return.failure');
});
