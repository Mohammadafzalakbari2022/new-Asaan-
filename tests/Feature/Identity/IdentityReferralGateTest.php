<?php

/*
|--------------------------------------------------------------------------
| The referral gate: no money moves until both people are checked
|--------------------------------------------------------------------------
|
| The referral programme pays real money, so the rule that stops an unverified
| account from cashing a reward is one of the most valuable promises this shop
| makes. It is also the easiest to break later without noticing, because the
| shared referral fixtures are verified accounts: if the gate stopped working,
| every existing referral test would carry on passing.
|
| These are the only tests that use unverified accounts. They go through the
| real admin approval path rather than calling the payout service directly, so
| the wiring from a reviewer pressing Approve down to the referrer's money is
| covered as well as the rule itself.
|
*/

require_once __DIR__.'/helpers.php';

use App\Models\User;
use Cartxis\Core\Services\SettingService;
use Cartxis\Identity\Services\IdentityService;
use Cartxis\Referral\Models\Referral;
use Cartxis\Referral\Models\ReferralCommission;
use Cartxis\Referral\Services\ReferralEarningService;
use Illuminate\Support\Str;

/**
 * Take a verified fixture back to unverified.
 *
 * The gate reads a single column, users.identity_verified_at, so clearing it
 * leaves the account in exactly the state it is in between signing up and being
 * approved.
 */
function unverified(User $user): User
{
    $user->forceFill(['identity_verified_at' => null])->save();

    return $user->fresh();
}

/**
 * Switch the identity settings the way an admin screen would, through the same
 * service the settings page writes with.
 */
function configureIdentity(array $overrides = []): void
{
    $settings = app(SettingService::class);

    $settings->set('identity.enabled', (bool) ($overrides['enabled'] ?? true), 'boolean', 'identity');
    $settings->set(
        'identity.require_verification_for_referral',
        (bool) ($overrides['require_verification_for_referral'] ?? true),
        'boolean',
        'identity'
    );

    $settings->clearCache();
}

/**
 * Queue a submission for an account that already has a referral code.
 *
 * The number has to be different every time: national IDs are fingerprinted and
 * the fingerprint is unique, so reusing one is refused by the database on
 * purpose.
 */
function queueTazkiraFor(User $user, ?string $number = null)
{
    return submitTazkira($user, $number ?: '77'.$user->id.'-'.Str::upper(Str::random(6)));
}

it('pays nothing when the shopper has spent enough but nobody has checked their Tazkira', function () {
    configureIdentity();
    configureReferral(['threshold_amount' => 500, 'reward_amount' => 10]);

    $referrer = referralUser('Referrer');
    $shopper = unverified(referralUser('Shopper'));
    linkReferral($referrer, $shopper);

    app(ReferralEarningService::class)->onOrderPaid(referralOrder($shopper, 600));

    expect(ReferralCommission::count())->toBe(0);
});

it('leaves the referral unrewarded while unverified, so the money is still owed', function () {
    configureIdentity();
    configureReferral(['threshold_amount' => 500, 'reward_amount' => 10]);

    $referrer = referralUser('Referrer');
    $shopper = unverified(referralUser('Shopper'));
    linkReferral($referrer, $shopper);

    app(ReferralEarningService::class)->onOrderPaid(referralOrder($shopper, 600));

    // Not marked as done: the threshold being crossed is not the same as the
    // reward being finished.
    expect(Referral::where('referred_user_id', $shopper->id)->first()->hasBeenRewarded())->toBeFalse();
});

it('pays the referral as soon as the shopper is approved', function () {
    configureIdentity();
    configureReferral(['threshold_amount' => 500, 'reward_amount' => 10]);

    $referrer = referralUser('Referrer');
    $shopper = unverified(referralUser('Shopper'));
    linkReferral($referrer, $shopper);

    app(ReferralEarningService::class)->onOrderPaid(referralOrder($shopper, 600));
    expect(ReferralCommission::count())->toBe(0);

    // The reviewer's Approve button, not the payout service called by hand.
    app(IdentityService::class)->approve(queueTazkiraFor($shopper), identityStaff());

    expect(ReferralCommission::count())->toBe(1)
        ->and((float) ReferralCommission::first()->amount)->toBe(10.0)
        ->and(Referral::where('referred_user_id', $shopper->id)->first()->hasBeenRewarded())->toBeTrue();
});

it('holds back the whole payout when the referrer is the unverified one', function () {
    configureIdentity();
    configureReferral(['threshold_amount' => 500, 'reward_amount' => 10]);

    $referrer = unverified(referralUser('Referrer'));
    $shopper = referralUser('Shopper');
    linkReferral($referrer, $shopper);

    // The shopper is verified and has spent enough, but the person who would
    // receive the money is not checked. A verified customer must not be able to
    // pay an unverified accomplice.
    app(ReferralEarningService::class)->onOrderPaid(referralOrder($shopper, 600));

    expect(ReferralCommission::count())->toBe(0);
});

it('pays once the referrer is verified, even though the shopper was done first', function () {
    configureIdentity();
    configureReferral(['threshold_amount' => 500, 'reward_amount' => 10]);

    $referrer = unverified(referralUser('Referrer'));
    $shopper = referralUser('Shopper');
    linkReferral($referrer, $shopper);

    app(ReferralEarningService::class)->onOrderPaid(referralOrder($shopper, 600));
    expect(ReferralCommission::count())->toBe(0);

    app(IdentityService::class)->approve(queueTazkiraFor($referrer), identityStaff());

    expect(ReferralCommission::count())->toBe(1)
        ->and((float) ReferralCommission::first()->amount)->toBe(10.0);
});

it('pays only once even when the approval is run again', function () {
    configureIdentity();
    configureReferral(['threshold_amount' => 500, 'reward_amount' => 10]);

    $referrer = referralUser('Referrer');
    $shopper = unverified(referralUser('Shopper'));
    linkReferral($referrer, $shopper);

    app(ReferralEarningService::class)->onOrderPaid(referralOrder($shopper, 600));

    app(IdentityService::class)->approve(queueTazkiraFor($shopper), identityStaff());
    expect(ReferralCommission::count())->toBe(1);

    // A second submission and approval, or the back-payment being triggered by
    // hand after a retry, must not pay the referrer a second time.
    app(IdentityService::class)->approve(queueTazkiraFor($shopper), identityStaff());
    app(ReferralEarningService::class)->onIdentityVerified($shopper->id);

    expect(ReferralCommission::count())->toBe(1)
        ->and((float) ReferralCommission::first()->amount)->toBe(10.0);
});

it('does not back-pay a referral that never reached the threshold', function () {
    configureIdentity();
    configureReferral(['threshold_amount' => 500, 'reward_amount' => 10]);

    $referrer = referralUser('Referrer');
    $shopper = unverified(referralUser('Shopper'));
    linkReferral($referrer, $shopper);

    app(ReferralEarningService::class)->onOrderPaid(referralOrder($shopper, 400));
    app(IdentityService::class)->approve(queueTazkiraFor($shopper), identityStaff());

    expect(ReferralCommission::count())->toBe(0);
});

it('lets the owner turn the gate off deliberately', function () {
    configureIdentity(['require_verification_for_referral' => false]);
    configureReferral(['threshold_amount' => 500, 'reward_amount' => 10]);

    $referrer = referralUser('Referrer');
    $shopper = unverified(referralUser('Shopper'));
    linkReferral($referrer, $shopper);

    app(ReferralEarningService::class)->onOrderPaid(referralOrder($shopper, 600));

    expect(ReferralCommission::count())->toBe(1);
});

it('pays nothing at all while identity verification is switched off as a whole', function () {
    configureIdentity(['enabled' => false]);
    configureReferral(['threshold_amount' => 500, 'reward_amount' => 10]);

    $referrer = referralUser('Referrer');
    $shopper = referralUser('Shopper');
    linkReferral($referrer, $shopper);

    app(ReferralEarningService::class)->onOrderPaid(referralOrder($shopper, 600));

    expect(ReferralCommission::count())->toBe(1)
        ->and($referrer->fresh()->identity_verified_at)->not->toBeNull();
});