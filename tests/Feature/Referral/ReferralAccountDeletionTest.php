<?php

use App\Models\User;
use Cartxis\Referral\Exceptions\ReferralBalanceOutstanding;

it('refuses to delete an account that still has credit to spend', function () {
    configureReferral();

    $user = referralUser();
    credit()->adminCredit($user->id, 250, 1, 'Test');

    $response = $this->actingAs($user)->delete(route('shop.account.profile.destroy'), [
        'password' => 'password',
    ]);

    expect($response->status())->toBe(302)
        ->and(User::where('id', $user->id)->exists())->toBeTrue();
});

it('tells the customer how much credit they are holding, and that it is theirs', function () {
    configureReferral();

    $user = referralUser();
    credit()->adminCredit($user->id, 250, 1, 'Test');

    $response = $this->actingAs($user)->delete(route('shop.account.profile.destroy'), [
        'password' => 'password',
    ]);

    $message = session('error');

    expect($message)->toContain('250')
        ->and(strtolower($message))->toContain('referral credit');
});

it('refuses to delete an account whose credit has not finished unlocking', function () {
    configureReferral(['lock_days' => 180]);

    $referrer = referralUser('Referrer');
    $shopper = referralUser('Shopper');
    linkReferral($referrer, $shopper);
    referralOrder($shopper, 600, 'paid');

    $response = $this->actingAs($referrer)->delete(route('shop.account.profile.destroy'), [
        'password' => 'password',
    ]);

    expect($response->status())->toBe(302)
        ->and(User::where('id', $referrer->id)->exists())->toBeTrue()
        ->and(session('error'))->toContain('unlock');
});

it('lets an account be deleted once every credit has been spent', function () {
    configureReferral();

    $user = referralUser();
    credit()->adminCredit($user->id, 250, 1, 'Test');
    credit()->spend($user->id, 250);

    $response = $this->actingAs($user)->delete(route('shop.account.profile.destroy'), [
        'password' => 'password',
    ]);

    expect($response->status())->toBe(302)
        ->and(session('error'))->toBeNull()
        ->and(User::where('id', $user->id)->exists())->toBeFalse();
});

it('lets an account with no credit at all be deleted straight away', function () {
    configureReferral();

    $user = referralUser();

    $this->actingAs($user)->delete(route('shop.account.profile.destroy'), [
        'password' => 'password',
    ]);

    expect(User::where('id', $user->id)->exists())->toBeFalse();
});

it('lets an account be deleted when the admin turns the block off', function () {
    configureReferral(['block_account_deletion' => false]);

    $user = referralUser();
    credit()->adminCredit($user->id, 250, 1, 'Test');

    $this->actingAs($user)->delete(route('shop.account.profile.destroy'), [
        'password' => 'password',
    ]);

    expect(User::where('id', $user->id)->exists())->toBeFalse();
});

it('still asks for the password before saying anything about credit', function () {
    configureReferral();

    $user = referralUser();
    credit()->adminCredit($user->id, 250, 1, 'Test');

    $this->actingAs($user)
        ->delete(route('shop.account.profile.destroy'), ['password' => 'wrong-password'])
        ->assertSessionHasErrors('password');

    expect(User::where('id', $user->id)->exists())->toBeTrue();
});

it('stops the account being deleted by code, not only through the shop', function () {
    configureReferral();

    $user = referralUser();
    credit()->adminCredit($user->id, 250, 1, 'Test');

    expect(fn () => $user->delete())->toThrow(ReferralBalanceOutstanding::class)
        ->and(User::where('id', $user->id)->exists())->toBeTrue();
});

it('stops a mass delete from quietly wiping credit', function () {
    configureReferral();

    $holder = referralUser('Holder');
    credit()->adminCredit($holder->id, 250, 1, 'Test');

    expect(fn () => User::query()->whereKey($holder->id)->delete())
        ->toThrow(ReferralBalanceOutstanding::class);
});

it('names the amount in the error so whoever is doing the deletion knows what is owed', function () {
    configureReferral();

    $user = referralUser();
    credit()->adminCredit($user->id, 175.50, 1, 'Test');

    try {
        $user->delete();
        $this->fail('Expected the delete to be refused.');
    } catch (ReferralBalanceOutstanding $e) {
        expect($e->available)->toBe(175.50)
            ->and($e->locked)->toBe(0.0)
            ->and($e->userId)->toBe($user->id)
            ->and($e->getMessage())->toContain('175.50');
    }
});

it('allows a deliberate erasure when the caller says so, and keeps the reason in code', function () {
    configureReferral();

    $user = referralUser();
    credit()->adminCredit($user->id, 250, 1, 'Test');

    $previous = User::allowDeletingWithReferralHistory();

    try {
        $user->delete();
    } finally {
        User::allowDeletingWithReferralHistory($previous);
    }

    expect(User::where('id', $user->id)->exists())->toBeFalse()
        // The flag must not stay on, or the next account would slip through.
        ->and(User::allowDeletingWithReferralHistory())->toBeFalse();
});
