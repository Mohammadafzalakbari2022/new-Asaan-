<?php

use Cartxis\Identity\Http\Controllers\IdentityVerificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customer identity verification routes
|--------------------------------------------------------------------------
|
| Required from inside the storefront's account group, not registered on its own
| from the service provider, so these pages get the same theme layout, session
| handling and auth middleware as the rest of the customer's account area.
|
| The account group supplies the 'account' prefix, the 'auth' middleware and the
| 'shop.account.' name prefix, so nothing is repeated here.
|
*/

Route::get('/identity', [IdentityVerificationController::class, 'index'])
    ->name('identity.index');

Route::post('/identity', [IdentityVerificationController::class, 'store'])
    ->middleware('throttle:identity-submit')
    ->name('identity.store');