<?php

use Cartxis\Referral\Http\Controllers\ReferralDashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customer referral routes
|--------------------------------------------------------------------------
|
| Required from inside the storefront's own web routes, not registered on its
| own, so these pages get the same theme layout, session handling and auth
| middleware as the rest of the customer's account area. Registering a second
| route file from a service provider would build a parallel set of routes with
| none of that, and the referral page would render without the shop's header.
|
| The account group in the storefront's web.php supplies the 'account' prefix,
| the 'auth' middleware and the 'shop.account.' name prefix, so nothing is
| repeated here.
|
*/

Route::get('/referrals', [ReferralDashboardController::class, 'index'])
    ->name('referrals.index');
