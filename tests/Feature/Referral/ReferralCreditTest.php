<?php

use App\Models\User;
use Cartxis\Referral\Models\ReferralCommission;
use Cartxis\Referral\Models\ReferralLedgerEntry;
use Cartxis\Referral\Services\ReferralEarningService;

it('starts every customer at nothing, with no rows to explain', function () {
    $user = referralUser();

    expect(credit()->availableBalance($user->id))->toBe(0.0)
        ->and(credit()->totalBalance($user->id))->toBe(0.0)
        ->and(ReferralLedgerEntry::where('user_id', $user->id)->count())->toBe(0);
});

it('lets an admin add credit by hand with a reason on the record', function () {
    $user = referralUser();

    credit()->adminCredit($user->id, 250, 1, 'Goodwill for a delayed order');

    $entry = ReferralLedgerEntry::where('user_id', $user->id)->first();

    expect(credit()->availableBalance($user->id))->toBe(250.0)
        ->and($entry->type)->toBe(ReferralLedgerEntry::TYPE_ADMIN_CREDIT)
        ->and($entry->admin_id)->toBe(1)
        ->and($entry->reason)->toBe('Goodwill for a delayed order');
});

it('keeps locked credit out of the spendable balance', function () {
    configureReferral(['lock_days' => 30, 'threshold_amount' => 500]);

    $referrer = referralUser('Referrer');
    $shopper = referralUser('Shopper');
    linkReferral($referrer, $shopper);

    referralOrder($shopper, 600, 'paid');

    expect(credit()->availableBalance($referrer->id))->toBe(0.0)
        ->and(credit()->lockedBalance($referrer->id))->toBe(10.0);

    $this->travel(31)->days();

    expect(credit()->availableBalance($referrer->id))->toBe(10.0)
        ->and(credit()->lockedBalance($referrer->id))->toBe(0.0)
        ->and(credit()->totalBalance($referrer->id))->toBe(10.0);
});

it('never spends more than the customer actually has', function () {
    $user = referralUser();
    credit()->adminCredit($user->id, 30, 1, 'Test');

    $taken = credit()->spend($user->id, 100);

    expect($taken)->toBe(30.0)
        ->and(credit()->availableBalance($user->id))->toBe(0.0);
});

it('never lets a balance go below zero', function () {
    $user = referralUser();
    credit()->adminCredit($user->id, 30, 1, 'Test');

    expect(credit()->spend($user->id, 20))->toBe(20.0)
        // Asking for 20 more when only 10 is left takes 10, not 20.
        ->and(credit()->spend($user->id, 20))->toBe(10.0)
        ->and(credit()->totalBalance($user->id))->toBe(0.0)
        ->and(credit()->availableBalance($user->id))->toBe(0.0);
});

it('takes nothing when there is no credit at all', function () {
    $user = referralUser();

    expect(credit()->spend($user->id, 50))->toBe(0.0)
        ->and(ReferralLedgerEntry::count())->toBe(0);
});

it('refuses to spend a negative or zero amount', function () {
    $user = referralUser();
    credit()->adminCredit($user->id, 50, 1, 'Test');

    expect(credit()->spend($user->id, -10))->toBe(0.0)
        ->and(credit()->spend($user->id, 0))->toBe(0.0)
        ->and(credit()->availableBalance($user->id))->toBe(50.0);
});

it('will not let one customer spend credit belonging to another', function () {
    $rich = referralUser('Rich');
    $poor = referralUser('Poor');
    credit()->adminCredit($rich->id, 500, 1, 'Test');

    credit()->spend($poor->id, 100);

    expect(credit()->availableBalance($poor->id))->toBe(0.0)
        ->and(credit()->availableBalance($rich->id))->toBe(500.0);
});

it('records a running total after every single movement', function () {
    $user = referralUser();

    credit()->adminCredit($user->id, 100, 1, 'First');
    credit()->spend($user->id, 30);
    credit()->adminCredit($user->id, 50, 1, 'Second');
    credit()->spend($user->id, 20);

    $afterEach = ReferralLedgerEntry::where('user_id', $user->id)
        ->orderBy('id')
        ->pluck('balance_after')
        ->map(fn ($v) => (float) $v)
        ->all();

    expect($afterEach)->toBe([100.0, 70.0, 120.0, 100.0])
        ->and(credit()->totalBalance($user->id))->toBe(100.0);
});

it('keeps a written reason on every hand-issued movement', function () {
    $user = referralUser();
    $admin = User::create([
        'name' => 'Admin',
        'email' => 'admin@example.test',
        'password' => 'password',
        'role' => 'admin',
        'is_active' => true,
    ]);

    credit()->adminCredit($user->id, 50, $admin->id, 'Compensation for a broken item');
    credit()->adminDebit($user->id, 20, $admin->id, 'Credit issued by mistake');

    expect(ReferralLedgerEntry::where('user_id', $user->id)->whereNotNull('reason')->count())->toBe(2)
        ->and(credit()->availableBalance($user->id))->toBe(30.0);
});

it('takes back credit an admin gives wrongly, without going below what is there', function () {
    $user = referralUser();
    credit()->adminCredit($user->id, 30, 1, 'Test');

    credit()->adminDebit($user->id, 50, 1, 'Issued in error');

    expect(credit()->availableBalance($user->id))->toBe(-20.0);
});

it('gives back a reward that an admin reverses, with the reason kept', function () {
    configureReferral(['threshold_amount' => 500, 'reward_amount' => 10, 'lock_days' => 0]);

    $referrer = referralUser('Referrer');
    $shopper = referralUser('Shopper');
    linkReferral($referrer, $shopper);

    $order = referralOrder($shopper, 600, 'paid');
    expect(credit()->availableBalance($referrer->id))->toBe(10.0);

    $commission = ReferralCommission::first();
    credit()->reverseCommission($commission, 'Shopper returned everything', 1);

    expect(credit()->availableBalance($referrer->id))->toBe(0.0)
        ->and($commission->fresh()->isReversed())->toBeTrue()
        ->and($commission->fresh()->reversal_reason)->toBe('Shopper returned everything');
});

it('does not take the same reward back twice', function () {
    configureReferral(['threshold_amount' => 500, 'reward_amount' => 10, 'lock_days' => 0]);

    $referrer = referralUser('Referrer');
    $shopper = referralUser('Shopper');
    linkReferral($referrer, $shopper);
    referralOrder($shopper, 600, 'paid');

    $commission = ReferralCommission::first();

    credit()->reverseCommission($commission, 'First reversal', 1);
    credit()->reverseCommission($commission, 'Second reversal', 1);

    expect(credit()->totalBalance($referrer->id))->toBe(0.0)
        ->and(ReferralLedgerEntry::where('type', ReferralLedgerEntry::TYPE_REVERSED)->count())->toBe(1);
});

it('treats locked credit as still belonging to the customer when checking for a balance', function () {
    configureReferral(['lock_days' => 180, 'threshold_amount' => 500]);

    $referrer = referralUser('Referrer');
    $shopper = referralUser('Shopper');
    linkReferral($referrer, $shopper);
    referralOrder($shopper, 600, 'paid');

    expect(credit()->availableBalance($referrer->id))->toBe(0.0)
        ->and(credit()->hasAnyBalance($referrer->id))->toBeTrue();
});

it('reports nothing left to owe once everything is spent', function () {
    $user = referralUser();
    credit()->adminCredit($user->id, 40, 1, 'Test');
    credit()->spend($user->id, 40);

    expect(credit()->hasAnyBalance($user->id))->toBeFalse()
        ->and(credit()->totalSpent($user->id))->toBe(40.0)
        ->and(credit()->lifetimeEarned($user->id))->toBe(40.0);
});

it('gives the same answer every time the same award is recalculated', function () {
    configureReferral(['threshold_amount' => 500, 'reward_amount' => 10]);

    $referrer = referralUser('Referrer');
    $shopper = referralUser('Shopper');
    linkReferral($referrer, $shopper);
    $order = referralOrder($shopper, 600, 'paid');

    $first = app(ReferralEarningService::class)->onOrderPaid($order);
    $ledgerCount = ReferralLedgerEntry::where('user_id', $referrer->id)->count();

    app(ReferralEarningService::class)->onOrderPaid($order);
    app(ReferralEarningService::class)->onOrderPaid($order);

    expect(ReferralLedgerEntry::where('user_id', $referrer->id)->count())->toBe($ledgerCount)
        ->and($ledgerCount)->toBe(1);
});
