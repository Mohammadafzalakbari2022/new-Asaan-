<?php

use Cartxis\Referral\Http\Controllers\Admin\ReferralCommissionController;
use Cartxis\Referral\Http\Controllers\Admin\ReferralCreditController;
use Cartxis\Referral\Http\Controllers\Admin\ReferralOverviewController;
use Cartxis\Referral\Http\Controllers\Admin\ReferralPeopleController;
use Cartxis\Referral\Http\Controllers\Admin\ReferralSettingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Referral Admin Routes
|--------------------------------------------------------------------------
|
| Sits under Marketing, next to Coupons and Promotions. The parent menu item is
| seeded by the referral package's menu migration.
|
*/

Route::middleware(['web', 'auth:admin'])->prefix('admin/marketing/referrals')->name('admin.marketing.referrals.')->group(function () {

    Route::get('/', [ReferralOverviewController::class, 'index'])->name('index');
    Route::get('/top-referrers', [ReferralOverviewController::class, 'topReferrers'])->name('top-referrers');
    Route::get('/people', [ReferralPeopleController::class, 'index'])->name('people.index');
    Route::get('/people/{user}', [ReferralPeopleController::class, 'show'])->name('people.show');

    Route::get('/commissions', [ReferralCommissionController::class, 'index'])->name('commissions.index');
    Route::post('/commissions/{commission}/reverse', [ReferralCommissionController::class, 'reverse'])
        ->name('commissions.reverse');

    Route::post('/credit', [ReferralCreditController::class, 'store'])->name('credit.store');

    Route::get('/settings', [ReferralSettingController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [ReferralSettingController::class, 'update'])->name('settings.update');
});
