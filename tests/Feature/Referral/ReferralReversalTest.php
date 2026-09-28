<?php

use Cartxis\Referral\Models\ReferralCommission;
use Cartxis\Referral\Models\ReferralLedgerEntry;
use Cartxis\Shop\Models\Order;

it('takes the reward back when the order is refunded', function () {
    configureReferral(['lock_days' => 0, 'threshold_amount' => 500]);

    $referrer = referralUser('Referrer');
    $shopper = referralUser('Shopper');
    linkReferral($referrer, $shopper);

    $order = referralOrder($shopper, 600, 'paid');
    expect(credit()->availableBalance($referrer->id))->toBe(10.0);

    $order->update(['payment_status' => Order::PAYMENT_REFUNDED]);

    expect(credit()->availableBalance($referrer->id))->toBe(0.0)
        ->and(ReferralCommission::first()->isReversed())->toBeTrue();
});

it('explains on the record why a reward was taken back after a refund', function () {
    configureReferral(['lock_days' => 0, 'threshold_amount' => 500]);

    $referrer = referralUser('Referrer');
    $shopper = referralUser('Shopper');
    linkReferral($referrer, $shopper);

    $order = referralOrder($shopper, 600, 'paid');
    $order->update(['payment_status' => Order::PAYMENT_REFUNDED]);

    $entry = ReferralLedgerEntry::where('type', ReferralLedgerEntry::TYPE_REVERSED)->first();

    expect($entry->reason)->toContain($order->order_number)
        ->and($entry->reason)->toContain('refunded');
});

it('does not take the same reward back twice when a refund is recorded again', function () {
    configureReferral(['lock_days' => 0, 'threshold_amount' => 500]);

    $referrer = referralUser('Referrer');
    $shopper = referralUser('Shopper');
    linkReferral($referrer, $shopper);

    $order = referralOrder($shopper, 600, 'paid');
    $order->update(['payment_status' => Order::PAYMENT_REFUNDED]);
    $order->update(['payment_status' => Order::PAYMENT_REFUNDED]);
    $order->update(['payment_status' => Order::PAYMENT_PAID]);

    expect(ReferralLedgerEntry::where('type', ReferralLedgerEntry::TYPE_REVERSED)->count())->toBe(1)
        ->and(credit()->totalBalance($referrer->id))->toBe(0.0);
});

it('gives the credit back when an order is cancelled before it is paid', function () {
    configureReferral();

    $user = referralUser();
    credit()->adminCredit($user->id, 400, 1, 'Test');

    $order = checkoutOrder($user);
    expect(referralCheckout()->reserve($user, 400, $order))->toBe(400.0)
        ->and(credit()->availableBalance($user->id))->toBe(0.0);

    $order->update(['status' => Order::STATUS_CANCELLED]);

    expect(credit()->availableBalance($user->id))->toBe(400.0)
        ->and(credit()->totalBalance($user->id))->toBe(400.0);
});

it('explains on the record why credit came back after a cancellation', function () {
    configureReferral();

    $user = referralUser();
    credit()->adminCredit($user->id, 400, 1, 'Test');

    $order = checkoutOrder($user);
    referralCheckout()->reserve($user, 400, $order);
    $order->update(['status' => Order::STATUS_CANCELLED]);

    $entry = ReferralLedgerEntry::where('type', ReferralLedgerEntry::TYPE_REVERSED)->first();

    expect($entry->reason)->toContain('cancelled');
});

it('does not give credit back twice for one cancelled order', function () {
    configureReferral();

    $user = referralUser();
    credit()->adminCredit($user->id, 400, 1, 'Test');

    $order = checkoutOrder($user);
    referralCheckout()->reserve($user, 400, $order);
    $order->update(['status' => Order::STATUS_CANCELLED]);
    $order->update(['status' => Order::STATUS_CANCELLED]);

    expect(credit()->availableBalance($user->id))->toBe(400.0)
        ->and(ReferralLedgerEntry::where('type', ReferralLedgerEntry::TYPE_REVERSED)->count())->toBe(1);
});

it('lets a cancelled order be retried with the same credit afterwards', function () {
    configureReferral();

    $user = referralUser();
    credit()->adminCredit($user->id, 400, 1, 'Test');

    $failed = checkoutOrder($user);
    referralCheckout()->reserve($user, 400, $failed);
    $failed->update(['status' => Order::STATUS_CANCELLED]);

    $retried = checkoutOrder($user);

    expect(referralCheckout()->reserve($user, 400, $retried))->toBe(400.0)
        ->and(credit()->availableBalance($user->id))->toBe(0.0)
        ->and((float) $retried->fresh()->total)->toBe(700.0);
});

it('leaves a completed order alone when it is cancelled nowhere near any credit', function () {
    configureReferral();

    $user = referralUser();
    $order = checkoutOrder($user);

    $order->update(['status' => Order::STATUS_COMPLETED]);

    expect(credit()->totalBalance($user->id))->toBe(0.0)
        ->and(ReferralLedgerEntry::count())->toBe(0);
});

it('does not hand out a reward for an order that was paid and then cancelled', function () {
    configureReferral(['lock_days' => 0, 'threshold_amount' => 500]);

    $referrer = referralUser('Referrer');
    $shopper = referralUser('Shopper');
    linkReferral($referrer, $shopper);

    $order = referralOrder($shopper, 600, 'pending');
    $order->update(['status' => Order::STATUS_CANCELLED]);
    $order->update(['payment_status' => Order::PAYMENT_PAID]);

    expect(ReferralCommission::count())->toBe(0)
        ->and(credit()->totalBalance($referrer->id))->toBe(0.0);
});
