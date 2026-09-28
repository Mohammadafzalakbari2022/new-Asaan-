<?php

use App\Models\User;
use Cartxis\Referral\Services\ReferralCheckoutService;
use Cartxis\Shop\Models\Order;

it('offers nothing to a customer with no credit', function () {
    configureReferral();

    $user = referralUser();

    expect(referralCheckout()->availableFor($user))->toBe(0.0)
        ->and(referralCheckout()->applicableAmount($user, 1000))->toBe(0.0);
});

it('offers credit to nobody who is not signed in', function () {
    configureReferral();

    $user = referralUser();
    credit()->adminCredit($user->id, 500, 1, 'Test');

    expect(referralCheckout()->availableFor(null))->toBe(0.0)
        ->and(referralCheckout()->applicableAmount(null, 1000))->toBe(0.0);
});

it('offers nothing at all while the programme is switched off', function () {
    configureReferral(['enabled' => false]);

    $user = referralUser();
    credit()->adminCredit($user->id, 500, 1, 'Test');

    expect(referralCheckout()->availableFor($user))->toBe(0.0)
        ->and(referralCheckout()->applicableAmount($user, 1000))->toBe(0.0);
});

it('offers the whole balance when the order is big enough', function () {
    configureReferral();

    $user = referralUser();
    credit()->adminCredit($user->id, 400, 1, 'Test');

    expect(referralCheckout()->applicableAmount($user, 1000))->toBe(400.0);
});

it('offers the whole balance when the order is exactly the balance', function () {
    configureReferral();

    $user = referralUser();
    credit()->adminCredit($user->id, 400, 1, 'Test');

    expect(referralCheckout()->applicableAmount($user, 400))->toBe(400.0);
});

it('never offers more credit than the goods are worth', function () {
    configureReferral();

    $user = referralUser();
    credit()->adminCredit($user->id, 900, 1, 'Test');

    expect(referralCheckout()->applicableAmount($user, 250))->toBe(250.0);
});

it('caps credit at the percentage the admin set', function () {
    configureReferral(['credit_max_percent_of_order' => 50]);

    $user = referralUser();
    credit()->adminCredit($user->id, 900, 1, 'Test');

    // 1000 of goods, credit may cover half.
    expect(referralCheckout()->applicableAmount($user, 1000))->toBe(500.0);
});

it('leaves locked credit out of what checkout offers', function () {
    configureReferral(['lock_days' => 180, 'threshold_amount' => 500]);

    $referrer = referralUser('Referrer');
    $shopper = referralUser('Shopper');
    linkReferral($referrer, $shopper);
    referralOrder($shopper, 600, 'paid');

    expect(referralCheckout()->availableFor($referrer))->toBe(0.0)
        ->and(referralCheckout()->applicableAmount($referrer, 1000))->toBe(0.0);
});

it('takes nothing when the customer did not ask for it', function () {
    configureReferral();

    $user = referralUser();
    credit()->adminCredit($user->id, 400, 1, 'Test');

    $order = checkoutOrder($user);

    expect(referralCheckout()->reserve($user, 0, $order))->toBe(0.0)
        ->and((float) $order->fresh()->credit_applied)->toBe(0.0)
        ->and((float) $order->fresh()->total)->toBe(1100.0);
});

it('takes only what the customer ticked, not the whole balance', function () {
    configureReferral();

    $user = referralUser();
    credit()->adminCredit($user->id, 400, 1, 'Test');

    $order = checkoutOrder($user);
    $taken = referralCheckout()->reserve($user, 50, $order);

    expect($taken)->toBe(50.0)
        ->and((float) $order->fresh()->credit_applied)->toBe(50.0)
        ->and((float) $order->fresh()->total)->toBe(1050.0)
        ->and(credit()->availableBalance($user->id))->toBe(350.0);
});

it('takes only up to the cap even if the customer asks for everything', function () {
    configureReferral(['credit_max_percent_of_order' => 50]);

    $user = referralUser();
    credit()->adminCredit($user->id, 900, 1, 'Test');

    $order = checkoutOrder($user);

    expect(referralCheckout()->reserve($user, 900, $order))->toBe(500.0)
        ->and(credit()->availableBalance($user->id))->toBe(400.0);
});

it('never lets credit wipe out the delivery fee', function () {
    configureReferral();

    $user = referralUser();
    credit()->adminCredit($user->id, 5000, 1, 'Test');

    // 1000 of goods plus 100 delivery. Credit may cover the goods, not the delivery.
    $order = checkoutOrder($user);
    $taken = referralCheckout()->reserve($user, 5000, $order);

    expect($taken)->toBe(1000.0)
        ->and((float) $order->fresh()->total)->toBe(100.0);
});

it('does not wipe out tax either', function () {
    configureReferral();

    $user = referralUser();
    credit()->adminCredit($user->id, 5000, 1, 'Test');

    $order = checkoutOrder($user, [
        'subtotal' => 1000, 'tax' => 100, 'shipping_cost' => 0, 'discount' => 0,
    ]);

    expect(referralCheckout()->reserve($user, 5000, $order))->toBe(1100.0)
        ->and((float) $order->fresh()->total)->toBe(0.0);
});

it('applies credit after a coupon, never before', function () {
    configureReferral();

    $user = referralUser();
    credit()->adminCredit($user->id, 400, 1, 'Test');

    // 1000 of goods, 300 off with a coupon, 100 delivery.
    $order = checkoutOrder($user, [
        'subtotal' => 1000, 'tax' => 0, 'shipping_cost' => 100, 'discount' => 300,
    ]);

    expect((float) $order->total)->toBe(800.0);

    $taken = referralCheckout()->reserve($user, 400, $order);

    // Coupon brought the goods to 700, so 400 of credit still leaves 300 to pay
    // plus 100 delivery. Applying credit first would have wrongly eaten delivery.
    expect($taken)->toBe(400.0)
        ->and((float) $order->fresh()->total)->toBe(400.0);
});

it('never takes more than the order still owes', function () {
    configureReferral();

    $user = referralUser();
    credit()->adminCredit($user->id, 5000, 1, 'Test');

    $order = checkoutOrder($user, [
        'subtotal' => 100, 'tax' => 0, 'shipping_cost' => 0, 'discount' => 0,
    ]);

    expect(referralCheckout()->reserve($user, 5000, $order))->toBe(100.0)
        ->and((float) $order->fresh()->total)->toBe(0.0);
});

it('gives the same credit to only one of two orders placed at the same time', function () {
    configureReferral();

    $user = referralUser();
    credit()->adminCredit($user->id, 300, 1, 'Test');

    $first = checkoutOrder($user);
    $second = checkoutOrder($user);

    $a = referralCheckout()->reserve($user, 300, $first);
    $b = referralCheckout()->reserve($user, 300, $second);

    expect($a + $b)->toBe(300.0)
        ->and(credit()->availableBalance($user->id))->toBe(0.0)
        ->and(credit()->totalBalance($user->id))->toBe(0.0);
});

it('takes nothing for a guest who is not signed in', function () {
    configureReferral();

    $order = Order::create([
        'user_id' => null,
        'customer_email' => 'guest@example.test',
        'order_number' => 'ORD-' . strtoupper(Str::random(10)),
        'status' => 'pending',
        'subtotal' => 1000,
        'tax' => 0,
        'shipping_cost' => 0,
        'discount' => 0,
        'credit_applied' => 0,
        'total' => 1000,
        'payment_method' => 'cod',
        'payment_status' => 'pending',
    ]);

    expect(referralCheckout()->reserve(null, 500, $order))->toBe(0.0)
        ->and((float) $order->fresh()->credit_applied)->toBe(0.0);
});

it('takes nothing for a negative or silly request', function () {
    configureReferral();

    $user = referralUser();
    credit()->adminCredit($user->id, 300, 1, 'Test');

    $order = checkoutOrder($user);

    expect(referralCheckout()->reserve($user, -100, $order))->toBe(0.0)
        ->and(credit()->availableBalance($user->id))->toBe(300.0);
});
