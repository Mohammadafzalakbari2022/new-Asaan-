<?php

require_once __DIR__ . '/helpers.php';

use Inertia\Testing\AssertableInertia as Assert;

/*
|--------------------------------------------------------------------------
| The review queue actually opens
|--------------------------------------------------------------------------
|
| The customer page was covered; the admin pages were only ever driven through
| the service, so a controller that could not be built (a missing Request
| import, say) still shipped. These request the real routes.
|
*/

it('opens the identity review queue for a reviewer', function () {
    $this->actingAs(identityStaff(), 'admin')
        ->get('/admin/customers/identity')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Customer/Identity/Index')
            ->has('verifications')
            ->has('counts')
            ->where('filters.status', null)
        );
});

it('opens a single submission for a reviewer', function () {
    $verification = submitTazkira(identityCustomer(), '100234567');

    $this->actingAs(identityStaff(), 'admin')
        ->get('/admin/customers/identity/' . $verification->id)
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Customer/Identity/Show')
            ->where('verification.id', $verification->id)
            ->where('verification.status', 'pending')
        );
});

it('keeps a customer out of the review queue', function () {
    $this->actingAs(identityCustomer())
        ->get('/admin/customers/identity')
        ->assertRedirect();
});
