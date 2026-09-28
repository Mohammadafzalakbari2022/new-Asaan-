<?php

use App\Models\User;

function adminUser(): User
{
    return User::create([
        'name' => 'Shop Admin',
        'email' => 'admin@example.test',
        'password' => 'password',
        'role' => 'admin',
        'is_active' => true,
    ]);
}

/*
|--------------------------------------------------------------------------
| Only staff may see or change the programme
|--------------------------------------------------------------------------
*/

it('keeps guests out of the referral programme screens', function (string $path) {
    $this->get($path)->assertRedirect();
})->with([
    '/admin/marketing/referrals',
    '/admin/marketing/referrals/people',
    '/admin/marketing/referrals/commissions',
    '/admin/marketing/referrals/settings',
    '/admin/marketing/referrals/top-referrers',
]);

it('keeps ordinary customers out of the referral programme screens', function (string $path) {
    // The admin guard sends anyone who is not staff back to the admin sign-in
    // page. It does not return 403, which is deliberate: a 403 would confirm the
    // screen exists.
    $this->actingAs(referralUser())->get($path)->assertRedirect();
})->with([
    '/admin/marketing/referrals',
    '/admin/marketing/referrals/people',
    '/admin/marketing/referrals/commissions',
    '/admin/marketing/referrals/settings',
]);

it('keeps ordinary customers out even when they are signed in on the admin guard', function () {
    $this->actingAs(referralUser(), 'admin')
        ->get('/admin/marketing/referrals')
        ->assertRedirect();
});

it('lets a signed-in customer reach no referral data at all', function () {
    configureReferral();

    $referrer = referralUser('Referrer');
    $shopper = referralUser('Shopper');
    linkReferral($referrer, $shopper);
    referralOrder($shopper, 600, 'paid');

    $response = $this->actingAs($shopper)->get('/admin/marketing/referrals/people');

    expect($response->status())->toBe(302)
        ->and($response->headers->get('location'))->not->toContain('refunds');
});

it('stops a customer changing the programme settings', function () {
    configureReferral(['reward_amount' => 10]);

    $this->actingAs(referralUser())
        ->put(route('admin.marketing.referrals.settings.update'), [
            'enabled' => true,
            'reward_amount' => 9999,
            'threshold_amount' => 500,
            'lock_days' => 180,
            'level_shares' => [100],
            'reward_mode' => 'once_per_person',
            'allow_admin_credit' => true,
            'block_account_deletion' => true,
            'credit_max_percent_of_order' => 100,
        ])
        ->assertRedirect();

    expect(app(Cartxis\Referral\Services\ReferralSettings::class)->rewardAmount())->toBe(10.0);
});

it('stops a customer giving themselves credit by hand', function () {
    $customer = referralUser();

    $this->actingAs($customer)
        ->post(route('admin.marketing.referrals.credit.store'), [
            'user_id' => $customer->id,
            'type' => 'admin_credit',
            'amount' => 5000,
            'reason' => 'Because I asked nicely',
        ])
        ->assertRedirect();

    expect(credit()->totalBalance($customer->id))->toBe(0.0);
});

it('stops a customer reversing a reward', function () {
    configureReferral(['lock_days' => 0, 'threshold_amount' => 500]);

    $referrer = referralUser('Referrer');
    $shopper = referralUser('Shopper');
    linkReferral($referrer, $shopper);
    referralOrder($shopper, 600, 'paid');

    $commission = Cartxis\Referral\Models\ReferralCommission::first();

    $this->actingAs($shopper)
        ->post(route('admin.marketing.referrals.commissions.reverse', $commission), [
            'reason' => 'Not my friend',
        ])
        ->assertRedirect();

    expect(credit()->availableBalance($referrer->id))->toBe(10.0);
});

it('lets an admin into every programme screen', function () {
    configureReferral();

    $admin = adminUser();

    $this->actingAs($admin, 'admin')->get('/admin/marketing/referrals')->assertSuccessful();
    $this->actingAs($admin, 'admin')->get('/admin/marketing/referrals/settings')->assertSuccessful();
    $this->actingAs($admin, 'admin')->get('/admin/marketing/referrals/top-referrers')->assertSuccessful();
    $this->actingAs($admin, 'admin')->get('/admin/marketing/referrals/people')->assertSuccessful();
    $this->actingAs($admin, 'admin')->get('/admin/marketing/referrals/commissions')->assertSuccessful();
});

/*
|--------------------------------------------------------------------------
| Defence in depth: the request classes refuse non-staff on their own
|--------------------------------------------------------------------------
|
| The admin guard already stops a customer at the door. These check the second
| lock on the same door, so a future route that forgets the middleware still
| cannot change money or settings.
|
*/

it('refuses settings changes from anybody who is not an admin', function () {
    $request = Cartxis\Referral\Http\Requests\SaveReferralSettingsRequest::create('/', 'PUT', []);
    $request->setUserResolver(fn () => referralUser());

    expect($request->authorize())->toBeFalse();
});

it('refuses settings changes from nobody at all', function () {
    $request = Cartxis\Referral\Http\Requests\SaveReferralSettingsRequest::create('/', 'PUT', []);
    $request->setUserResolver(fn () => null);

    expect($request->authorize())->toBeFalse();
});

it('accepts settings changes from an admin', function () {
    $request = Cartxis\Referral\Http\Requests\SaveReferralSettingsRequest::create('/', 'PUT', []);
    $request->setUserResolver(fn () => adminUser());

    expect($request->authorize())->toBeTrue();
});

it('refuses hand-issued credit from anybody who is not an admin', function () {
    $request = Cartxis\Referral\Http\Requests\StoreReferralCreditRequest::create('/', 'POST', []);
    $request->setUserResolver(fn () => referralUser());

    expect($request->authorize())->toBeFalse();
});

it('refuses reward reversals from anybody who is not an admin', function () {
    $request = Cartxis\Referral\Http\Requests\ReverseReferralCommissionRequest::create('/', 'POST', []);
    $request->setUserResolver(fn () => referralUser());

    expect($request->authorize())->toBeFalse();
});

it('accepts hand-issued credit from an admin', function () {
    $request = Cartxis\Referral\Http\Requests\StoreReferralCreditRequest::create('/', 'POST', []);
    $request->setUserResolver(fn () => adminUser());

    expect($request->authorize())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Settings validation
|--------------------------------------------------------------------------
*/

it('refuses a level split that does not add up to one hundred', function () {
    configureReferral();

    $this->actingAs(adminUser(), 'admin')
        ->put(route('admin.marketing.referrals.settings.update'), [
            'enabled' => true,
            'reward_amount' => 10,
            'threshold_amount' => 500,
            'lock_days' => 180,
            'level_shares' => [60, 60],
            'reward_mode' => 'once_per_person',
            'allow_admin_credit' => true,
            'block_account_deletion' => true,
            'credit_max_percent_of_order' => 100,
        ])
        ->assertSessionHasErrors('level_shares');
});

it('refuses a negative reward', function () {
    configureReferral();

    $this->actingAs(adminUser(), 'admin')
        ->put(route('admin.marketing.referrals.settings.update'), [
            'enabled' => true,
            'reward_amount' => -50,
            'threshold_amount' => 500,
            'lock_days' => 180,
            'level_shares' => [100],
            'reward_mode' => 'once_per_person',
            'allow_admin_credit' => true,
            'block_account_deletion' => true,
            'credit_max_percent_of_order' => 100,
        ])
        ->assertSessionHasErrors('reward_amount');
});

it('refuses a lock period that is not a whole number of days', function () {
    configureReferral();

    $this->actingAs(adminUser(), 'admin')
        ->put(route('admin.marketing.referrals.settings.update'), [
            'enabled' => true,
            'reward_amount' => 10,
            'threshold_amount' => 500,
            'lock_days' => -5,
            'level_shares' => [100],
            'reward_mode' => 'once_per_person',
            'allow_admin_credit' => true,
            'block_account_deletion' => true,
            'credit_max_percent_of_order' => 100,
        ])
        ->assertSessionHasErrors('lock_days');
});

it('refuses a credit cap above one hundred percent', function () {
    configureReferral();

    $this->actingAs(adminUser(), 'admin')
        ->put(route('admin.marketing.referrals.settings.update'), [
            'enabled' => true,
            'reward_amount' => 10,
            'threshold_amount' => 500,
            'lock_days' => 180,
            'level_shares' => [100],
            'reward_mode' => 'once_per_person',
            'allow_admin_credit' => true,
            'block_account_deletion' => true,
            'credit_max_percent_of_order' => 150,
        ])
        ->assertSessionHasErrors('credit_max_percent_of_order');
});

it('saves a valid set of settings and applies them straight away', function () {
    configureReferral();

    $this->actingAs(adminUser(), 'admin')
        ->put(route('admin.marketing.referrals.settings.update'), [
            'enabled' => true,
            'reward_amount' => 25,
            'threshold_amount' => 1000,
            'lock_days' => 30,
            'level_shares' => [60, 40],
            'reward_mode' => 'once_per_person',
            'allow_admin_credit' => false,
            'block_account_deletion' => false,
            'credit_max_percent_of_order' => 75,
        ])
        ->assertSessionHasNoErrors();

    $settings = app(Cartxis\Referral\Services\ReferralSettings::class);

    expect($settings->rewardAmount())->toBe(25.0)
        ->and($settings->thresholdAmount())->toBe(1000.0)
        ->and($settings->lockDays())->toBe(30)
        ->and($settings->maxLevels())->toBe(2)
        ->and($settings->adminCreditAllowed())->toBeFalse()
        ->and($settings->blocksAccountDeletion())->toBeFalse()
        ->and($settings->creditMaxPercentOfOrder())->toBe(75);
});

/*
|--------------------------------------------------------------------------
| Hand-issued credit
|--------------------------------------------------------------------------
*/

it('refuses hand-issued credit with no reason written down', function () {
    configureReferral();

    $customer = referralUser();

    $this->actingAs(adminUser(), 'admin')
        ->post(route('admin.marketing.referrals.credit.store'), [
            'user_id' => $customer->id,
            'type' => 'admin_credit',
            'amount' => 100,
            'reason' => '',
        ])
        ->assertSessionHasErrors('reason');

    expect(credit()->totalBalance($customer->id))->toBe(0.0);
});

it('refuses hand-issued credit for an account that does not exist', function () {
    configureReferral();

    $this->actingAs(adminUser(), 'admin')
        ->post(route('admin.marketing.referrals.credit.store'), [
            'user_id' => 999999,
            'type' => 'admin_credit',
            'amount' => 100,
            'reason' => 'Goodwill gesture',
        ])
        ->assertSessionHasErrors('user_id');
});

it('refuses hand-issued credit of nothing or less', function () {
    configureReferral();

    $customer = referralUser();

    $this->actingAs(adminUser(), 'admin')
        ->post(route('admin.marketing.referrals.credit.store'), [
            'user_id' => $customer->id,
            'type' => 'admin_credit',
            'amount' => 0,
            'reason' => 'Goodwill gesture',
        ])
        ->assertSessionHasErrors('amount');
});

it('refuses hand-issued credit once the admin has switched the feature off', function () {
    configureReferral(['allow_admin_credit' => false]);

    $customer = referralUser();

    $this->actingAs(adminUser(), 'admin')
        ->post(route('admin.marketing.referrals.credit.store'), [
            'user_id' => $customer->id,
            'type' => 'admin_credit',
            'amount' => 100,
            'reason' => 'Goodwill gesture',
        ])
        ->assertSessionHas('error');

    expect(credit()->totalBalance($customer->id))->toBe(0.0);
});

it('refuses to take back by hand more credit than the customer has', function () {
    configureReferral();

    $customer = referralUser();
    credit()->adminCredit($customer->id, 50, 1, 'Test');

    $this->actingAs(adminUser(), 'admin')
        ->post(route('admin.marketing.referrals.credit.store'), [
            'user_id' => $customer->id,
            'type' => 'admin_debit',
            'amount' => 500,
            'reason' => 'Taking it all back',
        ])
        ->assertSessionHas('error');

    expect(credit()->availableBalance($customer->id))->toBe(50.0);
});

it('takes credit back by hand when the amount is there to take', function () {
    configureReferral();

    $customer = referralUser();
    credit()->adminCredit($customer->id, 50, 1, 'Test');

    $this->actingAs(adminUser(), 'admin')
        ->post(route('admin.marketing.referrals.credit.store'), [
            'user_id' => $customer->id,
            'type' => 'admin_debit',
            'amount' => 20,
            'reason' => 'Issued in error',
        ])
        ->assertSessionHasNoErrors();

    expect(credit()->availableBalance($customer->id))->toBe(30.0);
});

it('refuses to reverse a reward with no reason written down', function () {
    configureReferral(['lock_days' => 0, 'threshold_amount' => 500]);

    $referrer = referralUser('Referrer');
    $shopper = referralUser('Shopper');
    linkReferral($referrer, $shopper);
    referralOrder($shopper, 600, 'paid');

    $commission = Cartxis\Referral\Models\ReferralCommission::first();

    $this->actingAs(adminUser(), 'admin')
        ->post(route('admin.marketing.referrals.commissions.reverse', $commission), ['reason' => ''])
        ->assertSessionHasErrors('reason');

    expect(credit()->availableBalance($referrer->id))->toBe(10.0);
});

it('reverses a reward for an admin and says how much was taken', function () {
    configureReferral(['lock_days' => 0, 'threshold_amount' => 500]);

    $referrer = referralUser('Referrer');
    $shopper = referralUser('Shopper');
    linkReferral($referrer, $shopper);
    referralOrder($shopper, 600, 'paid');

    $commission = Cartxis\Referral\Models\ReferralCommission::first();

    $this->actingAs(adminUser(), 'admin')
        ->post(route('admin.marketing.referrals.commissions.reverse', $commission), [
            'reason' => 'Shopper returned the goods',
        ])
        ->assertSessionHasNoErrors();

    expect(credit()->availableBalance($referrer->id))->toBe(0.0);
});

/*
|--------------------------------------------------------------------------
| The customer's own referral page
|--------------------------------------------------------------------------
*/

it('keeps a signed-out visitor out of their own referral page', function () {
    $this->get('/account/referrals')->assertRedirect();
});

it('shows a customer their code, their credit and who invited them', function () {
    configureReferral(['reward_amount' => 15, 'threshold_amount' => 800, 'lock_days' => 45]);

    $referrer = referralUser('Referrer');
    $customer = referralUser('Customer');
    linkReferral($referrer, $customer);
    credit()->adminCredit($customer->id, 120, 1, 'Test');

    $this->actingAs($customer)
        ->get('/account/referrals')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('referral.code', $customer->referralCode->code)
            ->where('referral.share_link', fn ($link) => str_contains($link, '?ref='))
            ->where('balances.available', 120)
            ->where('programme.reward_amount', 15)
            ->where('programme.threshold_amount', 800)
            ->where('programme.lock_days', 45)
            ->where('referredBy.name', 'Referrer')
        );
});

it('does not show a full name in the invited list', function () {
    configureReferral();

    $referrer = referralUser('Referrer');
    $customer = referralUser('Quiet Customer');
    linkReferral($referrer, $customer);

    $response = $this->actingAs($referrer)->get('/account/referrals');

    $response->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('invited.0.name', 'Q**** C*******')
        );

    // The point of the test: the real name never reaches the browser at all.
    expect($response->getContent())->not->toContain('Quiet Customer');
});

it('tells a visitor who followed a link what the offer is', function () {
    configureReferral(['reward_amount' => 20, 'threshold_amount' => 500]);

    $this->get('/referral/ABC-12345')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('code', 'ABC-12345')
            ->where('programme.reward_amount', 20)
            ->where('programme.threshold_amount', 500)
        );
});

it('also accepts the code in the query string, the other shape people paste', function () {
    configureReferral();

    $this->get('/referral?ref=XYZ-98765')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('code', 'XYZ-98765'));
});
