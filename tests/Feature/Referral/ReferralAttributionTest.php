<?php

use App\Models\User;
use Cartxis\Referral\Models\Referral;
use Cartxis\Referral\Models\ReferralCommission;
use Cartxis\Referral\Models\ReferralLedgerEntry;
use Cartxis\Referral\Services\ReferralCodeService;
use Cartxis\Referral\Services\ReferralCreditService;
use Cartxis\Referral\Services\ReferralLinkService;

/*
|--------------------------------------------------------------------------
| The shared link
|--------------------------------------------------------------------------
|
| A referral link is only worth anything if the person who follows it ends up
| credited. The link used to point at the site root, so a visitor could land
| anywhere and the code was never recorded. These tests pin the link to the
| registration page, which is the page that captures it.
|
*/

it('sends a shared link to the registration page, not the shop home page', function () {
    configureReferral();

    $user = referralUser('Ahmad Rahimi');
    $code = $user->referralCode->code;

    $link = app(ReferralCodeService::class)->shareLinkForUser($user);

    expect($link)->toBe(rtrim((string) config('app.url'), '/').'/register?ref='.$code);
});

it('reads the registration path from the real route, so a moved route cannot break sent links', function () {
    configureReferral();

    $user = referralUser('Ahmad Rahimi');
    $link = app(ReferralCodeService::class)->shareLinkForUser($user);

    $register = app('router')->getRoutes()->getByName('register');

    expect($register)->not->toBeNull()
        ->and(parse_url($link, PHP_URL_PATH))->toBe('/'.$register->uri())
        ->and(parse_url($link, PHP_URL_PATH))->toBe('/register')
        ->and(parse_url($link, PHP_URL_QUERY))->toBe('ref='.$user->referralCode->code)
        ->and(parse_url($link, PHP_URL_SCHEME))->toBe(parse_url((string) config('app.url'), PHP_URL_SCHEME));
});

it('produces a link that can be pasted straight into a message app', function () {
    configureReferral();

    $link = app(ReferralCodeService::class)->shareLinkForUser(referralUser('Ahmad Rahimi'));

    // Absolute, http(s), no spaces, no line breaks: copy and paste has to work.
    expect($link)->toMatch('#^https?://\S+$#')
        ->and($link)->not->toContain(' ');
});

/*
|--------------------------------------------------------------------------
| Following the link
|--------------------------------------------------------------------------
*/

it('remembers the code when somebody follows the shared link to the registration page', function () {
    configureReferral();

    $referrer = referralUser('Ahmad Rahimi');
    $code = $referrer->referralCode->code;

    $this->get(route('register').'?ref='.$code)
        ->assertOk()
        ->assertSessionHas('referral_code.code', $code);

    expect(session('referral_referrer_name'))->toBe('Ahmad Rahimi');
});

it('counts the click on the code when the link is followed', function () {
    configureReferral();

    $referrer = referralUser('Ahmad Rahimi');
    $before = $referrer->referralCode->clicks;

    $this->get(route('register').'?ref='.$referrer->referralCode->code)->assertOk();

    expect($referrer->referralCode->fresh()->clicks)->toBe($before + 1);
});

it('ignores a link carrying a code that does not exist', function () {
    configureReferral();

    $this->get(route('register').'?ref=NOT-A-REAL-CODE')
        ->assertOk()
        ->assertSessionMissing('referral_code');
});

it('links the account to whoever sent them when they register from the link', function () {
    configureReferral();

    $referrer = referralUser('Ahmad Rahimi');

    // The whole visitor journey: open the link, fill the form, press send.
    $this->get(route('register').'?ref='.$referrer->referralCode->code)->assertOk();

    $this->post(route('register.store'), [
        'name' => 'Sharif Ahmadi',
        'email' => 'sharif@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('shop.account.dashboard', absolute: false));

    $referred = User::where('email', 'sharif@example.test')->firstOrFail();

    $referral = Referral::where('referred_user_id', $referred->id)->first();

    expect($referral)->not->toBeNull()
        ->and($referral->referrer_user_id)->toBe($referrer->id)
        ->and($referral->level)->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Typing the code by hand
|--------------------------------------------------------------------------
|
| A link pasted into a chat app regularly arrives as plain text with the query
| string stripped, so the code has to be typeable. A code that is wrong must
| never cost somebody their account.
|
*/

it('accepts a code typed into the registration form', function () {
    configureReferral();

    $referrer = referralUser('Ahmad Rahimi');

    $this->post(route('register.store'), [
        'name' => 'Sharif Ahmadi',
        'email' => 'sharif@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'referral_code' => $referrer->referralCode->code,
    ])->assertRedirect(route('shop.account.dashboard', absolute: false));

    $referred = User::where('email', 'sharif@example.test')->firstOrFail();

    expect(Referral::where('referred_user_id', $referred->id)->first()?->referrer_user_id)
        ->toBe($referrer->id);
});

it('accepts a typed code in any case, with stray spaces around it', function () {
    configureReferral();

    $referrer = referralUser('Ahmad Rahimi');
    $messy = '  '.strtolower($referrer->referralCode->code).'  ';

    $this->post(route('register.store'), [
        'name' => 'Sharif Ahmadi',
        'email' => 'sharif@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'referral_code' => $messy,
    ])->assertRedirect(route('shop.account.dashboard', absolute: false));

    $referred = User::where('email', 'sharif@example.test')->firstOrFail();

    expect(Referral::where('referred_user_id', $referred->id)->first()?->referrer_user_id)
        ->toBe($referrer->id);
});

it('completes the registration even when the typed code is not a real code', function () {
    configureReferral();

    $referrer = referralUser('Ahmad Rahimi');

    $response = $this->post(route('register.store'), [
        'name' => 'Sharif Ahmadi',
        'email' => 'sharif@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'referral_code' => 'DEFINITELY-NOT-A-CODE',
    ]);

    // No validation error, no redirect back to the form: the sale is not lost
    // over a mistyped code.
    $response->assertRedirect(route('shop.account.dashboard', absolute: false));
    $response->assertSessionHasNoErrors();
    $this->assertAuthenticated();

    expect(User::where('email', 'sharif@example.test')->exists())->toBeTrue()
        ->and(Referral::where('referrer_user_id', $referrer->id)->count())->toBe(0);
});

it('completes the registration when the typed code belongs to the person signing up', function () {
    configureReferral();

    $referrer = referralUser('Ahmad Rahimi');

    $response = $this->post(route('register.store'), [
        'name' => 'Ahmad Rahimi',
        'email' => strtolower($referrer->referralCode->code.'@example.test'),
        'password' => 'password',
        'password_confirmation' => 'password',
        'referral_code' => $referrer->referralCode->code,
    ]);

    $response->assertRedirect(route('shop.account.dashboard', absolute: false));
    $response->assertSessionHasNoErrors();

    expect(Referral::where('referred_user_id', $referrer->id)->count())->toBe(0);
});

it('completes the registration when the typed code is left empty', function () {
    configureReferral();

    $this->post(route('register.store'), [
        'name' => 'Sharif Ahmadi',
        'email' => 'sharif@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'referral_code' => '',
    ])->assertRedirect(route('shop.account.dashboard', absolute: false));

    expect(Referral::count())->toBe(0);
});

it('does not reuse a typed code for the next account on the same browser', function () {
    configureReferral();

    $referrer = referralUser('Ahmad Rahimi');

    $this->post(route('register.store'), [
        'name' => 'Sharif Ahmadi',
        'email' => 'sharif@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'referral_code' => 'DEFINITELY-NOT-A-CODE',
    ])->assertRedirect(route('shop.account.dashboard', absolute: false));

    $this->post('/logout');

    $this->post(route('register.store'), [
        'name' => 'Second Visitor',
        'email' => 'second@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('shop.account.dashboard', absolute: false));

    $second = User::where('email', 'second@example.test')->firstOrFail();

    expect(Referral::where('referred_user_id', $second->id)->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| "Invited by"
|--------------------------------------------------------------------------
*/

it('tells the registration page who invited the visitor', function () {
    configureReferral();

    $referrer = referralUser('Ahmad Rahimi');

    $this->get(route('register').'?ref='.$referrer->referralCode->code)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('referralReferrerName', 'Ahmad Rahimi'));
});

it('tells the registration page nobody invited the visitor when there is no link', function () {
    configureReferral();

    $this->get(route('register'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('referralReferrerName', null));
});

it('stops saying who invited the visitor once the invitation has been used', function () {
    configureReferral();

    $referrer = referralUser('Ahmad Rahimi');

    $this->get(route('register').'?ref='.$referrer->referralCode->code)->assertOk();

    $this->post(route('register.store'), [
        'name' => 'Sharif Ahmadi',
        'email' => 'sharif@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->post('/logout');

    // A second person on the same browser must not be greeted as a guest of the
    // first referrer, whose code has already been spent.
    $this->get(route('register'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('referralReferrerName', null));
});

/*
|--------------------------------------------------------------------------
| A link that is kept for thirty days
|--------------------------------------------------------------------------
*/

it('keeps a captured code waiting for thirty days, then drops it', function () {
    configureReferral();

    $referrer = referralUser('Ahmad Rahimi');
    $code = $referrer->referralCode->code;
    $links = app(ReferralLinkService::class);

    $links->rememberPendingCode($code);

    $this->travel(29)->days();
    expect($links->pendingCode())->toBe($code);

    $this->travel(2)->days();
    expect($links->pendingCode())->toBeNull();
});

it('still links somebody who browses for a month before signing up', function () {
    configureReferral();

    $referrer = referralUser('Ahmad Rahimi');
    $links = app(ReferralLinkService::class);

    $this->get(route('register').'?ref='.$referrer->referralCode->code)->assertOk();

    $this->travel(29)->days();

    $this->post(route('register.store'), [
        'name' => 'Sharif Ahmadi',
        'email' => 'sharif@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('shop.account.dashboard', absolute: false));

    $referred = User::where('email', 'sharif@example.test')->firstOrFail();

    expect(Referral::where('referred_user_id', $referred->id)->first()?->referrer_user_id)
        ->toBe($referrer->id);
});

/*
|--------------------------------------------------------------------------
| Nobody is paid for a sign-up
|--------------------------------------------------------------------------
|
| Only the person who sends the link earns, and only once the person they sent
| it has spent real money. A test that says "nothing is paid at signup" is what
| keeps that from quietly changing.
|
*/

it('pays the referrer nothing at the moment somebody signs up', function () {
    configureReferral(['threshold_amount' => 500, 'reward_amount' => 10]);

    $referrer = referralUser('Ahmad Rahimi');

    $this->get(route('register').'?ref='.$referrer->referralCode->code)->assertOk();

    $this->post(route('register.store'), [
        'name' => 'Sharif Ahmadi',
        'email' => 'sharif@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('shop.account.dashboard', absolute: false));

    $referred = User::where('email', 'sharif@example.test')->firstOrFail();
    $referral = Referral::where('referred_user_id', $referred->id)->firstOrFail();

    expect($referral->hasBeenRewarded())->toBeFalse()
        ->and($referral->rewarded_at)->toBeNull()
        ->and(ReferralCommission::count())->toBe(0)
        ->and(ReferralLedgerEntry::count())->toBe(0)
        ->and(app(ReferralCreditService::class)->totalBalance($referrer->id))->toBe(0.0);
});

it('pays the referrer nothing at signup even when a code was typed in by hand', function () {
    configureReferral(['threshold_amount' => 500, 'reward_amount' => 10]);

    $referrer = referralUser('Ahmad Rahimi');

    $this->post(route('register.store'), [
        'name' => 'Sharif Ahmadi',
        'email' => 'sharif@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'referral_code' => $referrer->referralCode->code,
    ]);

    $referred = User::where('email', 'sharif@example.test')->firstOrFail();
    $referral = Referral::where('referred_user_id', $referred->id)->firstOrFail();

    expect(ReferralCommission::count())->toBe(0)
        ->and(ReferralLedgerEntry::count())->toBe(0)
        ->and($referral->hasBeenRewarded())->toBeFalse()
        ->and(app(ReferralCreditService::class)->totalBalance($referrer->id))->toBe(0.0);
});

/*
|--------------------------------------------------------------------------
| The account is credited the moment it is created
|--------------------------------------------------------------------------
*/

it('writes the referral on the same request that creates the account', function () {
    configureReferral();

    $referrer = referralUser('Ahmad Rahimi');
    $links = app(ReferralLinkService::class);

    // A remembered code is enough on its own: no second job, no queue, nothing
    // to run afterwards. If this ever needs a queue the account would exist
    // with no referrer and nobody would notice.
    $links->rememberPendingCode($referrer->referralCode->code);

    $user = User::create([
        'name' => 'Sharif Ahmadi',
        'email' => 'sharif@example.test',
        'password' => 'password',
    ]);

    expect(Referral::where('referred_user_id', $user->id)->first()?->referrer_user_id)
        ->toBe($referrer->id);
});
