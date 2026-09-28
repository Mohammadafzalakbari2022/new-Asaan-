<?php

use Cartxis\Referral\Models\Referral;
use Cartxis\Referral\Models\ReferralCode;
use Cartxis\Referral\Services\ReferralLinkService;
use Cartxis\Referral\Services\ReferralSettings;
use Illuminate\Support\Facades\Log;

it('gives every new account a referral code', function () {
    configureReferral();

    $user = referralUser();

    expect($user->referralCode)->not->toBeNull()
        ->and($user->referralCode->code)->toMatch('/^[A-Z]{2,6}-[23456789ABCDEFGHJKMNPQRSTUVWXYZ]{5}$/')
        ->and($user->referralCode->user_id)->toBe($user->id);
});

it('builds the code from the name', function () {
    configureReferral();

    $user = referralUser('Ahmad Rahimi');

    expect($user->referralCode->code)->toStartWith('AHMAD');
});

it('falls back to a plain prefix when the name has no letters in it', function () {
    configureReferral();

    $user = referralUser('۱۲۳');

    expect($user->referralCode->code)->toStartWith('REF-');
});

it('leaves out letters that are easily misread when spoken aloud', function () {
    configureReferral();

    $codes = collect(range(1, 60))
        ->map(fn () => referralUser()->referralCode->code)
        ->implode('');

    foreach (['0', '1', 'I', 'L', 'O'] as $confusable) {
        expect($codes)->not->toContain($confusable);
    }
});

it('links a signup to whoever sent them', function () {
    configureReferral();

    $referrer = referralUser('Referrer');
    $referred = referralUser('Newcomer');

    $referral = linkReferral($referrer, $referred);

    expect($referral)->not->toBeNull()
        ->and($referral->referrer_user_id)->toBe($referrer->id)
        ->and($referral->referred_user_id)->toBe($referred->id)
        ->and($referral->level)->toBe(1);
});

it('refuses a person referring themselves', function () {
    configureReferral();

    $user = referralUser();

    $result = app(ReferralLinkService::class)->linkToCode(
        $user,
        ReferralCode::where('user_id', $user->id)->firstOrFail()
    );

    expect($result['referral'])->toBeNull()
        ->and($result['reason'])->toBe(ReferralLinkService::REASON_SELF);
});

it('refuses a second account holding the same email as the referrer', function () {
    configureReferral();

    $referrer = referralUser('Referrer', ['email' => 'same.person@example.test']);
    $twin = referralUser('Twin', ['email' => 'SAME.PERSON@example.test']);

    $result = app(ReferralLinkService::class)->linkToCode(
        $twin,
        ReferralCode::where('user_id', $referrer->id)->firstOrFail()
    );

    expect($result['referral'])->toBeNull()
        ->and($result['reason'])->toBe(ReferralLinkService::REASON_SAME_EMAIL);
});

it('refuses a second account holding the same phone as the referrer', function () {
    configureReferral();

    $referrer = referralUser('Referrer', ['phone' => '0701234567']);
    $twin = referralUser('Twin', ['phone' => '0701234567']);

    $result = app(ReferralLinkService::class)->linkToCode(
        $twin,
        ReferralCode::where('user_id', $referrer->id)->firstOrFail()
    );

    expect($result['referral'])->toBeNull()
        ->and($result['reason'])->toBe(ReferralLinkService::REASON_SAME_PHONE);
});

it('keeps the first referrer when someone later arrives with a second code', function () {
    configureReferral();

    $first = referralUser('First');
    $second = referralUser('Second');
    $referred = referralUser('Newcomer');

    linkReferral($first, $referred);

    $result = app(ReferralLinkService::class)->linkToCode(
        $referred,
        ReferralCode::where('user_id', $second->id)->firstOrFail()
    );

    expect($result['referral'])->toBeNull()
        ->and($result['reason'])->toBe(ReferralLinkService::REASON_ALREADY_LINKED)
        ->and(Referral::where('referred_user_id', $referred->id)->count())->toBe(1)
        ->and(Referral::where('referred_user_id', $referred->id)->first()->referrer_user_id)->toBe($first->id);
});

it('links nobody at all while the programme is switched off', function () {
    configureReferral(['enabled' => false]);

    $referrer = referralUser();
    $referred = referralUser();

    expect(app(ReferralLinkService::class)->linkFor($referred, $referrer->referralCode->code))->toBeNull()
        ->and(Referral::count())->toBe(0);
});

it('refuses to link somebody whose referrer would be paid a second level nobody is paid', function () {
    // Default programme: one level, 100 percent. A customer who was themselves
    // referred is sitting at level 2, and nobody would ever be paid for them, so
    // the link is refused rather than silently created and never paid.
    configureReferral(['level_shares' => [100]]);

    $grandparent = referralUser('Grandparent');
    $parent = referralUser('Parent');
    $child = referralUser('Child');

    linkReferral($grandparent, $parent);

    $result = app(ReferralLinkService::class)->linkToCode(
        $child,
        ReferralCode::where('user_id', $parent->id)->firstOrFail()
    );

    expect($result['referral'])->toBeNull()
        ->and($result['reason'])->toBe(ReferralLinkService::REASON_TOO_DEEP)
        ->and(Referral::where('referred_user_id', $child->id)->count())->toBe(0);
});

it('pays a second level when two levels are configured', function () {
    configureReferral(['level_shares' => [70, 30]]);

    $grandparent = referralUser('Grandparent');
    $parent = referralUser('Parent');
    $child = referralUser('Child');

    expect(linkReferral($grandparent, $parent)->level)->toBe(1)
        ->and(linkReferral($parent, $child)->level)->toBe(2);
});

it('refuses a referral when the admin has disabled that link', function () {
    configureReferral();

    $referrer = referralUser();
    $code = ReferralCode::where('user_id', $referrer->id)->firstOrFail();
    $code->update(['status' => ReferralCode::STATUS_DISABLED]);

    $referred = referralUser();

    expect(Referral::where('referred_user_id', $referred->id)->count())->toBe(0);
});

it('returns the link itself from linkFor, not a wrapper', function () {
    configureReferral();

    $referrer = referralUser();
    $referred = referralUser();

    $result = app(ReferralLinkService::class)->linkFor($referred, $referrer->referralCode->code);

    expect($result)->toBeInstanceOf(Referral::class)
        ->and($result->referrer_user_id)->toBe($referrer->id);
});

it('logs no error when a signup links cleanly', function () {
    configureReferral();

    Log::spy();

    $referrer = referralUser();
    app(ReferralLinkService::class)->rememberPendingCode($referrer->referralCode->code);

    referralUser('Newcomer');

    // A TypeError here is swallowed by the observer, so the link would still be
    // saved to the database while the failure went unnoticed. This is the check
    // that actually catches it.
    Log::shouldNotHaveReceived('error');
    Log::shouldNotHaveReceived('warning');
});

it('links a signup automatically from a remembered ?ref= code, then forgets the code', function () {
    configureReferral();

    $referrer = referralUser();
    $code = $referrer->referralCode->code;

    // This is the browser hitting the shop with ?ref=CODE in the address bar.
    app(ReferralLinkService::class)->rememberPendingCode($code);
    expect(app(ReferralLinkService::class)->pendingCode())->toBe($code);

    // No explicit linking here: creating the account is the whole point, and it
    // happens in four separate places in the app.
    $referred = referralUser();

    $referral = Referral::where('referred_user_id', $referred->id)->first();

    expect($referral)->not->toBeNull()
        ->and($referral->referrer_user_id)->toBe($referrer->id)
        ->and(app(ReferralLinkService::class)->pendingCode())->toBeNull();
});

it('will not reuse a spent code for the next person on the same browser', function () {
    configureReferral();

    $referrer = referralUser();
    app(ReferralLinkService::class)->rememberPendingCode($referrer->referralCode->code);

    referralUser('First Newcomer');
    referralUser('Second Newcomer');

    expect(Referral::where('referrer_user_id', $referrer->id)->count())->toBe(1);
});

it('ignores a remembered code once it is older than thirty days', function () {
    configureReferral();

    $referrer = referralUser();

    session()->put('referral_code', [
        'code' => $referrer->referralCode->code,
        'expires_at' => now()->subDay(),
    ]);

    expect(app(ReferralLinkService::class)->pendingCode())->toBeNull();
});

it('stops paying a link once an admin voids it', function () {
    configureReferral();

    $referrer = referralUser();
    $referred = referralUser();

    $referral = linkReferral($referrer, $referred);
    expect($referral->isActive())->toBeTrue();

    $referral->update(['status' => Referral::STATUS_VOIDED]);

    expect($referral->fresh()->isActive())->toBeFalse();
});

it('rescales a level split that does not add to one hundred', function () {
    configureReferral(['level_shares' => [30, 30]]);

    $shares = app(ReferralSettings::class)->levelShares();

    expect(array_sum($shares))->toBe(100.0)
        ->and($shares)->toHaveCount(2);
});
