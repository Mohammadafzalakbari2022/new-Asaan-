<?php

/**
 * Choosing a currency changes what a shopper READS. It changes nothing about
 * what the store stores, calculates, or charges.
 *
 * That distinction is the whole design, and it is easy to lose by accident:
 * one template that passes a converted number into a cart, or one service that
 * saves it, and the store now holds a price that disagrees with the afghani
 * figure it came from -- permanently, as soon as the owner corrects the rate.
 * So these tests convert a lot and then check the database did not move.
 */

declare(strict_types=1);

require_once __DIR__ . '/CurrencyTestHelpers.php';

use Cartxis\Core\Models\Currency;
use Cartxis\Core\Support\DisplayCurrency;
use Cartxis\Shop\Models\Order;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    currencySeed($this);
    setExchangeRate(71.0);
});

// ---------------------------------------------------------------------------
// Which currency is on screen
// ---------------------------------------------------------------------------

it('shows the afghani to a shopper who has chosen nothing', function () {
    expect(DisplayCurrency::resolve()->code)->toBe('AFN');
});

it('shows the dollar to a shopper who chose the dollar', function () {
    viewingUsd();

    expect(DisplayCurrency::resolve()->code)->toBe('USD');
});

it('ignores a currency the store does not sell in', function () {
    // A stale session from before the store was restricted, or a hand-crafted
    // cookie. Falling back to the afghani is the safe answer; rendering a euro
    // price is not.
    app('session')->start();
    app('session')->put(DisplayCurrency::SESSION_KEY, 'EUR');

    expect(DisplayCurrency::resolve()->code)->toBe('AFN');
});

it('ignores rubbish in the session rather than rendering nothing', function () {
    app('session')->start();
    app('session')->put(DisplayCurrency::SESSION_KEY, 'not-a-currency');

    expect(DisplayCurrency::resolve()->code)->toBe('AFN');
});

it('falls back to the default when the chosen currency has been switched off', function () {
    viewingUsd();

    // The owner turns the dollar off after a shopper picked it.
    currencyRow('USD')->update(['is_active' => false]);

    expect(DisplayCurrency::resolve()->code)->toBe('AFN');
});

it('refuses to remember a currency that is switched off', function () {
    currencyRow('USD')->update(['is_active' => false]);

    viewingUsd();

    expect(DisplayCurrency::resolve()->code)->toBe('AFN');
});

it('still prices in afghani when the currencies table is empty', function () {
    // A layout that asks "what currency is this?" during a half-finished
    // install must get an answer, not a fatal error.
    DB::table('currencies')->delete();

    $currency = DisplayCurrency::resolve();

    expect($currency->code)->toBe('AFN');
    expect($currency->formatAmount(500))->toBe("\u{060B}500");
});

// ---------------------------------------------------------------------------
// The arithmetic
// ---------------------------------------------------------------------------

it('converts afghani to dollars by dividing the rate', function () {
    // The stored rate means "1 USD = 71 AFN", so 7100 afghani is 100 dollars.
    // Dividing rather than multiplying is the whole point of storing it that
    // way: the owner types a number they already know.
    expect(DisplayCurrency::convert(7100, 'USD'))->toBe(100.0);
    expect(DisplayCurrency::convert(3550, 'USD'))->toBe(50.0);
    expect(DisplayCurrency::convert(71, 'USD'))->toBe(1.0);
});

it('leaves afghani untouched when afghani is what is being shown', function () {
    expect(DisplayCurrency::convert(7100, 'AFN'))->toBe(7100.0);
    expect(currencyRow('AFN')->convert(7100))->toBe(7100.0);
});

it('rounds a converted figure to what the currency can actually show', function () {
    // 100 AFN at 71 is 1.4084... dollars. The dollar has two decimals, so the
    // screen gets 1.41 and never a third place.
    expect(DisplayCurrency::convert(100, 'USD'))->toBe(1.41);
});

it('leaves afghani as afghani rather than pretending to convert it', function () {
    // There is no "convert to AFN" in this store -- the base currency is
    // already what everything is stored in -- so the base case has to be the
    // identity or a template would scale a price that was already right.
    expect(DisplayCurrency::convert(1, 'AFN'))->toBe(1.0);
    expect(DisplayCurrency::convert(1234, 'AFN'))->toBe(1234.0);
});

it('follows the rate the owner has set', function () {
    setExchangeRate(100.0);

    expect(DisplayCurrency::convert(5000, 'USD'))->toBe(50.0);
});

it('shows the afghani amount rather than a guess when the rate is unusable', function () {
    // A zero rate cannot be divided by, and guessing a price is worse than
    // showing the local one.
    currencyRow('USD')->update(['exchange_rate' => 0]);

    expect(DisplayCurrency::convert(7100, 'USD'))->toBe(7100.0);
});

// ---------------------------------------------------------------------------
// Display only. Nothing here may reach the database.
// ---------------------------------------------------------------------------

it('converts without touching the stored price', function () {
    $order = currencyOrder(['subtotal' => 7100, 'total' => 7100]);

    $shown = DisplayCurrency::convert((float) $order->total, 'USD');

    expect($shown)->toBe(100.0);

    // The order row is the canonical figure and it has not moved.
    expect((float) $order->fresh()->total)->toBe(7100.0);
});

it('converts without touching the product price', function () {
    $productId = DB::table('products')->insertGetId([
        'sku' => 'CUR-' . uniqid(),
        'name' => 'Carpet',
        'slug' => 'carpet-' . uniqid(),
        'price' => 7100,
        'status' => 'enabled',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $stored = (float) DB::table('products')->where('id', $productId)->value('price');

    DisplayCurrency::format($stored, 'USD');
    DisplayCurrency::convert($stored, 'USD');

    expect((float) DB::table('products')->where('id', $productId)->value('price'))->toBe(7100.0);
});

it('leaves the order total alone when the shopper is browsing in dollars', function () {
    $order = currencyOrder(['subtotal' => 7100, 'tax' => 0, 'shipping_cost' => 0, 'total' => 7100]);

    viewingUsd();

    // Everything the storefront would render for this order, converted.
    $subtotal = DisplayCurrency::format((float) $order->subtotal);
    $total = DisplayCurrency::format((float) $order->total);

    expect($subtotal)->toBe('$100.00');
    expect($total)->toBe('$100.00');

    // What is actually stored, and what will be charged, is still afghani.
    $fresh = $order->fresh();

    expect((float) $fresh->subtotal)->toBe(7100.0);
    expect((float) $fresh->total)->toBe(7100.0);
    expect(currencyRow('AFN')->formatAmount((float) $fresh->total))->toBe("\u{060B}7,100");
});

it('recalculates the screen, not the record, when the rate changes', function () {
    $order = currencyOrder(['subtotal' => 7100, 'total' => 7100]);

    viewingUsd();
    expect(DisplayCurrency::format((float) $order->total))->toBe('$100.00');

    // The owner corrects the rate. The stored afghani order is now worth a
    // different number of dollars, and it costs one update, not a migration.
    setExchangeRate(142.0);

    expect(DisplayCurrency::format((float) $order->total))->toBe('$50.00');
    expect((float) $order->fresh()->total)->toBe(7100.0);
});

it('does not write a converted figure back through the model either', function () {
    $usd = currencyRow('USD');

    // convert() is a method on the model; it must not be able to mark the model
    // dirty and get saved by whatever called it.
    $usd->convert(7100);

    expect($usd->isDirty())->toBeFalse();
});

// ---------------------------------------------------------------------------
// The switch itself
// ---------------------------------------------------------------------------

it('switches the shopper to dollars through the endpoint', function () {
    $this->postJson('/currency', ['code' => 'USD'])->assertOk();

    expect(DisplayCurrency::resolve()->code)->toBe('USD');
});

it('is not a GET, so a third-party page cannot flip somebody\u2019s currency', function () {
    // A GET would let any page do it with an <img> tag, silently changing what
    // a shopper believes they are about to be charged.
    $this->get('/currency?code=USD')->assertNotFound();

    expect(DisplayCurrency::resolve()->code)->toBe('AFN');
});

it('needs no account, because a shopper picks a currency before signing in', function () {
    $this->postJson('/currency', ['code' => 'USD'])->assertOk();

    expect(DisplayCurrency::resolve()->code)->toBe('USD');
});

it('switches back to the afghani', function () {
    $this->postJson('/currency', ['code' => 'USD'])->assertOk();
    $this->postJson('/currency', ['code' => 'AFN'])->assertOk();

    expect(DisplayCurrency::resolve()->code)->toBe('AFN');
});

it('tells a shopper plainly that a currency the store dropped is refused', function () {
    $this->postJson('/currency', ['code' => 'GBP'])
        ->assertStatus(422)
        ->assertJson([
            'success' => false,
            'supported' => ['AFN', 'USD'],
        ]);

    // And the choice did not stick.
    expect(DisplayCurrency::resolve()->code)->toBe('AFN');
});

it('refuses a currency that exists in the table but is switched off', function () {
    currencyRow('USD')->update(['is_active' => false]);

    $this->postJson('/currency', ['code' => 'USD'])->assertStatus(422);

    expect(DisplayCurrency::resolve()->code)->toBe('AFN');
});

it('insists on being told which currency', function () {
    $this->postJson('/currency', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('code');
});

it('is case-insensitive, because the picker sends whatever the code is', function () {
    $this->postJson('/currency', ['code' => 'usd'])->assertOk();

    expect(DisplayCurrency::resolve()->code)->toBe('USD');
    expect(Currency::isSupportedCode('usd'))->toBeTrue();
    expect(Currency::isSupportedCode('  afn '))->toBeTrue();
    expect(Currency::isSupportedCode('EUR'))->toBeFalse();
    expect(Currency::isSupportedCode(null))->toBeFalse();
});

// ---------------------------------------------------------------------------
// What the storefront is handed
// ---------------------------------------------------------------------------

it('keeps the shopper\u2019s choice in the session and nowhere else', function () {
    $this->postJson('/currency', ['code' => 'USD'])->assertOk();

    expect(session(DisplayCurrency::SESSION_KEY))->toBe('USD');

    // Nothing about the choice is written to the database. A currency is a
    // display preference, and a signed-out shopper switching to dollars must
    // not create a row anywhere.
    expect(DB::table('currencies')->count())->toBe(2);
});

it('hands the page the base currency, the two choices, the chosen one and the rate', function () {
    viewingUsd();

    $props = currencySharedProps();

    expect($props)->toHaveKeys(['currency', 'currencies', 'displayCurrency', 'usdToAfn']);

    // The base currency is AFN, whatever the shopper picked.
    expect($props['currency']['code'])->toBe('AFN');

    // The picker is exactly the two, afghani first.
    expect(array_column($props['currencies'], 'code'))->toBe(['AFN', 'USD']);

    // And "chosen" is one of those two, not a guess made by the frontend.
    expect($props['displayCurrency']['code'])->toBe('USD');
    expect(array_column($props['currencies'], 'code'))
        ->toContain($props['displayCurrency']['code']);

    expect($props['usdToAfn'])->toBe(71.0);
});

it('gives the frontend decimal places it can trust', function () {
    $props = currencySharedProps();

    $byCode = collect($props['currencies'])->keyBy('code');

    expect($byCode['AFN']['decimal_places'])->toBe(0);
    expect($byCode['USD']['decimal_places'])->toBe(2);
});

it('still works when the store has switched the dollar off', function () {
    currencyRow('USD')->update(['is_active' => false]);
    viewingUsd();

    $props = currencySharedProps();

    // The picker does not offer it, and the chosen currency cannot be the one
    // it just dropped.
    expect(array_column($props['currencies'], 'code'))->toBe(['AFN']);
    expect($props['displayCurrency']['code'])->toBe('AFN');
});
