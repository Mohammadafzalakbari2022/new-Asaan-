<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/*
|--------------------------------------------------------------------------
| Referral programme helpers
|--------------------------------------------------------------------------
|
| The referral rule spans four tables and two models, so every test needs the
| same fixtures. These build the minimum real thing: a user, an order, and a
| referral link, created the same way the application creates them.
|
*/

use App\Models\User;
use Cartxis\Referral\Models\Referral;
use Cartxis\Referral\Models\ReferralCode;
use Cartxis\Referral\Services\ReferralLinkService;
use Cartxis\Referral\Services\ReferralSettings;
use Cartxis\Shop\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A customer account, with a referral code already minted the way signup mints it.
 */
function referralUser(string $name = 'Test Customer', array $attributes = []): User
{
    $user = User::create(array_merge([
        'name' => $name,
        'email' => Str::lower(Str::random(8)) . '@example.test',
        'password' => 'password',
        'role' => 'customer',
        'is_active' => true,
    ], $attributes));

    app(\Cartxis\Referral\Services\ReferralCodeService::class)->forUser($user);

    return $user->fresh();
}

/**
 * Link $referred to $referrer, exactly as signup does.
 */
function linkReferral(User $referrer, User $referred, int $level = 1): Referral
{
    return app(ReferralLinkService::class)->linkToCode(
        $referred,
        ReferralCode::where('user_id', $referrer->id)->firstOrFail()
    )['referral'];
}

/**
 * An order for a user.
 *
 * Created unpaid, then moved to the requested payment status, because that is
 * the real path: the shop records an order first and the money lands later. It
 * also means the OrderObserver is genuinely exercised rather than bypassed.
 */
function referralOrder(
    User $user,
    float $total = 600.0,
    string $paymentStatus = 'paid',
    array $attributes = []
): Order {
    $order = Order::create(array_merge([
        'user_id' => $user->id,
        'customer_email' => $user->email,
        'order_number' => 'ORD-' . strtoupper(Str::random(10)),
        'status' => 'processing',
        'subtotal' => $total,
        'tax' => 0,
        'shipping_cost' => 0,
        'discount' => 0,
        'credit_applied' => 0,
        'total' => $total,
        'payment_method' => 'cod',
        'payment_status' => 'pending',
    ], $attributes));

    if ($paymentStatus !== 'pending') {
        $order->update(['payment_status' => $paymentStatus]);
    }

    return $order->fresh();
}

/**
 * A customer record attached to a user, the way guest checkout leaves one behind.
 */
function referralCustomer(User $user): int
{
    return DB::table('customers')->insertGetId([
        'user_id' => $user->id,
        'first_name' => explode(' ', $user->name)[0] ?? 'Test',
        'last_name' => 'Customer',
        'email' => $user->email,
        'is_active' => true,
        'is_guest' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * Turn the programme on with a known configuration for the test.
 */
function configureReferral(array $overrides = []): void
{
    app(ReferralSettings::class)->save(array_merge([
        'enabled' => true,
        'reward_amount' => 10.0,
        'threshold_amount' => 500.0,
        'lock_days' => 180,
        'level_shares' => [100],
        'reward_mode' => 'once_per_person',
        'allow_admin_credit' => true,
        'block_account_deletion' => true,
        'credit_max_percent_of_order' => 100,
    ], $overrides));
}

/**
 * The one place a credit balance changes. Tests use it directly rather than
 * going through a reward, so they can set up a balance without an order.
 */
function credit(): \Cartxis\Referral\Services\ReferralCreditService
{
    return app(\Cartxis\Referral\Services\ReferralCreditService::class);
}

/**
 * The checkout side of the programme.
 */
function referralCheckout(): \Cartxis\Referral\Services\ReferralCheckoutService
{
    return app(\Cartxis\Referral\Services\ReferralCheckoutService::class);
}

/**
 * An unpaid order, the way the shop records it before taking payment.
 */
function checkoutOrder(User $user, array $lines = []): Order
{
    $lines = $lines ?: ['subtotal' => 1000, 'tax' => 0, 'shipping_cost' => 100, 'discount' => 0];

    $goods = (float) $lines['subtotal'] + (float) $lines['tax'] - (float) $lines['discount'];
    $total = $goods + (float) $lines['shipping_cost'];

    return Order::create(array_merge([
        'user_id' => $user->id,
        'customer_email' => $user->email,
        'order_number' => 'ORD-' . strtoupper(Str::random(10)),
        'status' => 'pending',
        'payment_method' => 'cod',
        'payment_status' => 'pending',
    ], $lines, [
        'total' => $total,
    ]));
}
