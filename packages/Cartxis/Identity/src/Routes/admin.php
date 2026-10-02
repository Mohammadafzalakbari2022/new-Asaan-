<?php

use Cartxis\Identity\Http\Controllers\Admin\IdentityDocumentController;
use Cartxis\Identity\Http\Controllers\Admin\IdentityVerificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Identity verification admin routes
|--------------------------------------------------------------------------
|
| Under Customers, next to the customer list. The admin guard is what keeps
| customers out: it redirects rather than answering 403, so a 403 would not
| confirm that the screen exists. The approve and reject actions carry the same
| guard plus their own authorisation check inside the form request.
|
*/

Route::middleware(['web', 'auth:admin'])
    ->prefix('admin/customers/identity')
    ->name('admin.customers.identity.')
    ->group(function () {
        Route::get('/', [IdentityVerificationController::class, 'index'])->name('index');
        Route::get('/{verification}', [IdentityVerificationController::class, 'show'])->name('show');

        Route::post('/{verification}/approve', [IdentityVerificationController::class, 'approve'])
            ->name('approve');
        Route::post('/{verification}/reject', [IdentityVerificationController::class, 'reject'])
            ->name('reject');

        // Delete the picture, keep the record.
        Route::delete('/{verification}/document', [IdentityVerificationController::class, 'destroy'])
            ->name('document.destroy');

        // The document itself. This is the only way to read it: an authenticated
        // stream from the private disk, with no public URL anywhere.
        Route::get('/{verification}/document', [IdentityDocumentController::class, 'show'])
            ->name('document');
    });