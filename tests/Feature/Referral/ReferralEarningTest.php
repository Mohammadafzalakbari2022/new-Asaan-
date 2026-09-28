<?php

use Cartxis\Referral\Models\Referral;
use Cartxis\Referral\Models\ReferralCommission;
use Cartxis\Referral\Services\ReferralCreditService;
use Cartxis\Referral\Services\ReferralEarningService;
use Cartxis\Shop\Models\Order;

it('pays nothing before the referred customer has spent enough over their lifetime', function () {
    configureReferral(['threshold_amount' => 500]);

    $referrer = referralUser('Referrer');
    $referred = referralUser('Shopper');
    linkReferral($referrer, $referred);

    $order = referralOrder($referred, 400);
    app(ReferralEarningService::class)->onOrderPaid($order);

    expect(ReferralCommission::count())->toBe(0)
        ->and(Referral::where('referred_user_id', $referred->id)->first()->hasBeenRewarded())->toBeFalse();
});

it('pays once the lifetime total crosses the threshold, not on one big order', function () {
    configureReferral(['threshold_amount' => 500, 'reward_amount' => 10]);

    $referrer = referralUser('Referrer');
    $referred = referralUser('Shopper');
    linkReferral($referrer, $referred);

    app(ReferralEarningService::class)->onOrderPaid(referralOrder($referred, 200));
    expect(ReferralCommission::count())->toBe(0);

    app(ReferralEarningService::class)->onOrderPaid(referralOrder($referred, 200));
    expect(ReferralCommission::count())->toBe(0);

    // Third order takes the running total over the line.
    $third = referralOrder($referred, 150);
    app(ReferralEarningService::class)->onOrderPaid($third);

    expect(ReferralCommission::count())->toBe(1)
        ->and((float) ReferralCommission::first()->amount)->toBe(10.0);
});

it('never pays the same person twice, no matter how much they keep spending', function () {
    configureReferral(['threshold_amount' => 500]);

    $referrer = referralUser('Referrer');
    $referred = referralUser('Shopper');
    linkReferral($referrer, $referred);

    foreach (range(1, 6) as $ignored) {
        app(ReferralEarningService::class)->onOrderPaid(referralOrder($referred, 400));
    }

    expect(ReferralCommission::count())->toBe(1)
        ->and(app(ReferralCreditService::class)->availableBalance($referrer->id))->toBe(0.0)
        ->and(app(ReferralCreditService::class)->lockedBalance($referrer->id))->toBe(10.0);
});

it('pays nothing twice when the same payment lands twice', function () {
    configureReferral(['threshold_amount' => 500]);

    $referrer = referralUser('Referrer');
    $referred = referralUser('Shopper');
    linkReferral($referrer, $referred);

    $order = referralOrder($referred, 600);

    // A gateway that fires its callback twice must not double-pay a person.
    app(ReferralEarningService::class)->onOrderPaid($order);
    app(ReferralEarningService::class)->onOrderPaid($order);

    expect(ReferralCommission::count())->toBe(1)
        ->and(app(ReferralCreditService::class)->totalBalance($referrer->id))->toBe(10.0);
});

it('pays nothing at all while the programme is switched off', function () {
    configureReferral(['enabled' => false]);

    $referrer = referralUser('Referrer');
    $referred = referralUser('Shopper');
    linkReferral($referrer, $referred);

    app(ReferralEarningService::class)->onOrderPaid(referralOrder($referred, 5000));

    expect(ReferralCommission::count())->toBe(0);
});

it('does not count unpaid orders toward the threshold', function () {
    configureReferral(['threshold_amount' => 500]);

    $referrer = referralUser('Referrer');
    $referred = referralUser('Shopper');
    linkReferral($referrer, $referred);

    app(ReferralEarningService::class)->onOrderPaid(
        referralOrder($referred, 5000, 'pending')
    );

    expect(ReferralCommission::count())->toBe(0);
});

it('does not count a cancelled order toward the threshold', function () {
    configureReferral(['threshold_amount' => 500]);

    $referrer = referralUser('Referrer');
    $shopper = referralUser('Shopper');
    linkReferral($referrer, $shopper);

    $order = referralOrder($shopper, 5000, 'pending', ['status' => Order::STATUS_CANCELLED]);
    $order->update(['payment_status' => 'paid']);

    expect(ReferralCommission::count())->toBe(0);
});

it('pays the first referrer only when a person spends on somebody with no referrer', function () {
    configureReferral();

    $stranger = referralUser('No Referrer');
    referralOrder($stranger, 5000);

    expect(ReferralCommission::count())->toBe(0);
});

it('splits the reward across two levels when configured to', function () {
    configureReferral(['threshold_amount' => 500, 'reward_amount' => 100, 'level_shares' => [70, 30]]);

    $grandparent = referralUser('Grandparent');
    $parent = referralUser('Parent');
    $shopper = referralUser('Shopper');

    linkReferral($grandparent, $parent);
    linkReferral($parent, $shopper);

    app(ReferralEarningService::class)->onOrderPaid(referralOrder($shopper, 600));

    $grandparentAmount = (float) ReferralCommission::where('referrer_user_id', $grandparent->id)->value('amount');
    $parentAmount = (float) ReferralCommission::where('referrer_user_id', $parent->id)->value('amount');

    expect(ReferralCommission::count())->toBe(2)
        ->and($grandparentAmount)->toBe(30.0)
        ->and($parentAmount)->toBe(70.0);
});

it('pays only the levels that exist when there is nobody above them', function () {
    configureReferral(['threshold_amount' => 500, 'reward_amount' => 100, 'level_shares' => [70, 30]]);

    $parent = referralUser('Parent');
    $shopper = referralUser('Shopper');

    linkReferral($parent, $shopper);

    app(ReferralEarningService::class)->onOrderPaid(referralOrder($shopper, 600));

    // The first level exists, the second does not. The full reward goes to level 1
    // rather than being halved by a share for somebody who is not there.
    expect(ReferralCommission::count())->toBe(1)
        ->and((float) ReferralCommission::first()->amount)->toBe(70.0);
});

it('does not let credit paid by the shopper push the referrer over the threshold', function () {
    configureReferral(['threshold_amount' => 500]);

    $referrer = referralUser('Referrer');
    $shopper = referralUser('Shopper');
    linkReferral($referrer, $shopper);

    // Shopper buys 600 of goods but pays only 400 in cash, settling 200 with
    // credit. The goods are worth more than the threshold; the real money is not.
    referralOrder($shopper, 400, 'paid', [
        'subtotal' => 600,
        'total' => 400,
        'credit_applied' => 200,
    ]);

    expect(ReferralCommission::count())->toBe(0);

    // Another 150 of real cash takes the cash total past the line.
    referralOrder($shopper, 150, 'paid');

    expect(ReferralCommission::count())->toBe(1);
});

it('pays when the cash total lands exactly on the threshold', function () {
    configureReferral(['threshold_amount' => 500]);

    $referrer = referralUser('Referrer');
    $shopper = referralUser('Shopper');
    linkReferral($referrer, $shopper);

    referralOrder($shopper, 500, 'paid');

    expect(ReferralCommission::count())->toBe(1);
});

it('counts a guest order through the customer record', function () {
    configureReferral(['threshold_amount' => 500]);

    $referrer = referralUser('Referrer');
    $shopper = referralUser('Shopper');
    linkReferral($referrer, $shopper);

    $order = Order::create([
        'user_id' => null,
        'customer_id' => referralCustomer($shopper),
        'order_number' => 'ORD-GUEST-' . strtoupper(Str::random(8)),
        'status' => 'processing',
        'subtotal' => 600,
        'tax' => 0,
        'shipping_cost' => 0,
        'discount' => 0,
        'credit_applied' => 0,
        'total' => 600,
        'payment_method' => 'cod',
        'payment_status' => 'pending',
    ]);

    expect(ReferralCommission::count())->toBe(0);

    $order->update(['payment_status' => 'paid']);

    expect(ReferralCommission::count())->toBe(1)
        ->and(ReferralCommission::first()->referrer_user_id)->toBe($referrer->id);
});

it('locks the reward until the lock period is over', function () {
    configureReferral(['threshold_amount' => 500, 'reward_amount' => 10, 'lock_days' => 180]);

    $referrer = referralUser('Referrer');
    $shopper = referralUser('Shopper');
    linkReferral($referrer, $shopper);

    $order = referralOrder($shopper, 600);
    app(ReferralEarningService::class)->onOrderPaid($order);

    $credit = app(ReferralCreditService::class);

    expect($credit->availableBalance($referrer->id))->toBe(0.0)
        ->and($credit->lockedBalance($referrer->id))->toBe(10.0)
        ->and($credit->lifetimeEarned($referrer->id))->toBe(10.0);

    $this->travel(181)->days();

    expect($credit->availableBalance($referrer->id))->toBe(10.0)
        ->and($credit->lockedBalance($referrer->id))->toBe(0.0);
});

it('lets an admin change the reward for later referrals without touching earlier ones', function () {
    configureReferral(['threshold_amount' => 500, 'reward_amount' => 10]);

    $referrer = referralUser('Referrer');
    $first = referralUser('First Shopper');
    linkReferral($referrer, $first);
    app(ReferralEarningService::class)->onOrderPaid(referralOrder($first, 600));

    configureReferral(['threshold_amount' => 500, 'reward_amount' => 25]);

    $second = referralUser('Second Shopper');
    linkReferral($referrer, $second);
    app(ReferralEarningService::class)->onOrderPaid(referralOrder($second, 600));

    $amounts = ReferralCommission::orderBy('id')->pluck('amount')->map(fn ($a) => (float) $a)->all();

    expect($amounts)->toBe([10.0, 25.0])
        ->and(app(ReferralCreditService::class)->totalBalance($referrer->id))->toBe(35.0);
});

it('records the reward and share on the award, so later setting changes cannot rewrite history', function () {
    configureReferral(['threshold_amount' => 500, 'reward_amount' => 10, 'level_shares' => [100]]);

    $referrer = referralUser('Referrer');
    $shopper = referralUser('Shopper');
    linkReferral($referrer, $shopper);
    app(ReferralEarningService::class)->onOrderPaid(referralOrder($shopper, 600));

    configureReferral(['threshold_amount' => 500, 'reward_amount' => 999, 'level_shares' => [100]]);

    $commission = ReferralCommission::first();

    expect((float) $commission->reward_snapshot)->toBe(10.0)
        ->and((float) $commission->share_snapshot)->toBe(100.0)
        ->and((float) $commission->amount)->toBe(10.0);
});

it('pays automatically when an order is marked paid after the fact', function () {
    configureReferral(['threshold_amount' => 500]);

    $referrer = referralUser('Referrer');
    $shopper = referralUser('Shopper');
    linkReferral($referrer, $shopper);

    $order = referralOrder($shopper, 600, 'pending');
    expect(ReferralCommission::count())->toBe(0);

    $order->update(['payment_status' => 'paid']);

    expect(ReferralCommission::count())->toBe(1)
        ->and(ReferralCommission::first()->referrer_user_id)->toBe($referrer->id);
});

it('pays automatically when the order is created already paid', function () {
    configureReferral(['threshold_amount' => 500]);

    $referrer = referralUser('Referrer');
    $shopper = referralUser('Shopper');
    linkReferral($referrer, $shopper);

    // Some paths, such as the admin's "mark as paid" button, never pass through
    // an unpaid state at all, so the insert itself has to trigger the reward.
    Order::create([
        'user_id' => $shopper->id,
        'customer_email' => $shopper->email,
        'order_number' => 'ORD-' . strtoupper(Str::random(10)),
        'status' => 'processing',
        'subtotal' => 600,
        'tax' => 0,
        'shipping_cost' => 0,
        'discount' => 0,
        'credit_applied' => 0,
        'total' => 600,
        'payment_method' => 'cod',
        'payment_status' => 'paid',
    ]);

    expect(ReferralCommission::count())->toBe(1)
        ->and(ReferralCommission::first()->referrer_user_id)->toBe($referrer->id);
});
