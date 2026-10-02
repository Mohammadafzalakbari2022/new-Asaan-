<?php

use Cartxis\Core\Http\Controllers\CurrencyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Shopper Currency Routes
|--------------------------------------------------------------------------
|
| Deliberately unauthenticated and outside the auth group: a shopper picks
| their currency from the header before they have an account, and refusing to
| show prices until sign-in would be a bad trade for remembering a preference.
|
| The route is a POST because it writes to the session. A GET would let a
| third-party page flip somebody's currency with an <img> tag.
|
*/

Route::post('/currency', [CurrencyController::class, 'update'])->name('currency.update');
