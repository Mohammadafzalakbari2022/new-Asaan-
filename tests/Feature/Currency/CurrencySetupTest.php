<?php

/**
 * The store's money: what a fresh install ends up with, and what happens to
 * the 147 other currencies an older install is carrying.
 *
 * These are the decisions the whole currency feature rests on:
 *   - exactly two currencies are offered, AFN and USD
 *   - AFN is the default, and the base everything is stored in
 *   - AFN shows NO decimals, because the afghani has no practical subunit
 *   - the other currencies are switched off, never deleted
 */

declare(strict_types=1);

require_once __DIR__ . '/CurrencyTestHelpers.php';

use Cartxis\Core\Models\Currency;

it('offers exactly AFN and USD after a fresh migrate and seed', function () {
    currencySeed($this);

    $codes = Currency::selectable()->pluck('code')->all();

    sort($codes);

    expect($codes)->toBe(['AFN', 'USD']);
});

it('seeds no third currency, so the picker cannot offer one', function () {
    currencySeed($this);

    expect(Currency::count())->toBe(2);
});

it('makes the afghani the default and the only default', function () {
    currencySeed($this);

    $defaults = Currency::query()->default()->pluck('code')->all();

    expect($defaults)->toBe(['AFN']);
    expect(Currency::getDefault()->code)->toBe('AFN');
});

it('gives the afghani zero decimals and the dollar two', function () {
    currencySeed($this);

    $afn = currencyRow('AFN');
    $usd = currencyRow('USD');

    expect($afn->decimal_places)->toBe(0);
    expect($usd->decimal_places)->toBe(2);

    // The same answer through the method every screen actually calls.
    expect($afn->displayDecimals())->toBe(0);
    expect($usd->displayDecimals())->toBe(2);
});

it('keeps the afghani worth exactly one afghani', function () {
    currencySeed($this);

    expect((float) currencyRow('AFN')->exchange_rate)->toBe(1.0);
});

it('starts the dollar rate at a number the owner can edit', function () {
    currencySeed($this);

    $rate = (float) currencyRow('USD')->exchange_rate;

    expect($rate)->toBeGreaterThan(0);
    expect($rate)->toBe(Currency::DEFAULT_USD_TO_AFN);
});

it('switches off the leftover currencies instead of deleting them', function () {
    currencySeed($this);

    // What the old country-derived seeder left behind: a euro and a rupee, both
    // switched on.
    Currency::query()->create([
        'code' => 'EUR', 'name' => 'Euro', 'symbol' => '€',
        'symbol_position' => 'after', 'decimal_places' => 2,
        'exchange_rate' => 1.0, 'is_default' => false, 'is_active' => true, 'sort_order' => 9,
    ]);
    Currency::query()->create([
        'code' => 'INR', 'name' => 'Indian Rupee', 'symbol' => '₹',
        'symbol_position' => 'before', 'decimal_places' => 2,
        'exchange_rate' => 1.0, 'is_default' => false, 'is_active' => true, 'sort_order' => 10,
    ]);

    $migration = require __DIR__
        . '/../../../packages/Cartxis/Core/src/Database/Migrations/2026_10_01_000001_restrict_store_to_afn_and_usd.php';

    $migration->up();

    // Gone from the picker...
    expect(Currency::selectable()->pluck('code')->all())->toEqualCanonicalizing(['AFN', 'USD']);

    // ...but still on disk, so an order written in one of them can still
    // resolve the currency it was placed in.
    $eur = currencyRow('EUR');
    $inr = currencyRow('INR');

    expect($eur)->not->toBeNull();
    expect($inr)->not->toBeNull();
    expect($eur->is_active)->toBeFalse();
    expect($inr->is_active)->toBeFalse();

    // The row is readable, not blanked out.
    expect($eur->name)->toBe('Euro');
    expect($inr->symbol)->toBe('₹');
});

it('takes the default back off a store that had the dollar as its default', function () {
    currencySeed($this);

    // The old seeder's actual damage: USD flagged as the default, so every
    // afghani figure on the site was printed with a dollar sign.
    currencyRow('USD')->update(['is_default' => true]);
    currencyRow('AFN')->update(['is_default' => false]);

    $migration = require __DIR__
        . '/../../../packages/Cartxis/Core/src/Database/Migrations/2026_10_01_000001_restrict_store_to_afn_and_usd.php';

    $migration->up();

    expect(Currency::query()->default()->pluck('code')->all())->toBe(['AFN']);
    expect(Currency::getDefault()->code)->toBe('AFN');
});

it('repairs an afghani row that was hand-edited back to two decimals', function () {
    currencySeed($this);

    currencyRow('AFN')->update(['decimal_places' => 2, 'symbol' => 'Af']);

    $migration = require __DIR__
        . '/../../../packages/Cartxis/Core/src/Database/Migrations/2026_10_01_000001_restrict_store_to_afn_and_usd.php';

    $migration->up();

    $afn = currencyRow('AFN');

    expect($afn->decimal_places)->toBe(0);
    expect($afn->symbol)->toBe("\u{060B}");
});

it('does not stomp a rate the owner has already corrected', function () {
    currencySeed($this);

    // The store owner has been keeping up with the market.
    setExchangeRate(72.5);

    $migration = require __DIR__
        . '/../../../packages/Cartxis/Core/src/Database/Migrations/2026_10_01_000001_restrict_store_to_afn_and_usd.php';

    $migration->up();

    // Silently resetting the rate would change every converted price on the
    // site back to a number the owner has already rejected.
    expect((float) currencyRow('USD')->exchange_rate)->toBe(72.5);
});

it('re-seeding is safe and leaves the rate alone', function () {
    currencySeed($this);
    setExchangeRate(68.0);

    currencySeed($this);

    expect(Currency::count())->toBe(2);
    expect((float) currencyRow('USD')->exchange_rate)->toBe(68.0);
    expect(Currency::query()->default()->pluck('code')->all())->toBe(['AFN']);
});

it('re-seeding a store with two default currencies leaves only one', function () {
    currencySeed($this);

    // Written straight through the query builder, the way a botched import
    // would: two rows flagged default, bypassing the model's saving hook.
    Currency::query()->where('code', 'USD')->update(['is_default' => true]);
    Currency::query()->where('code', 'AFN')->update(['is_default' => true]);

    expect(Currency::query()->default()->count())->toBe(2);

    currencySeed($this);

    expect(Currency::query()->default()->pluck('code')->all())->toBe(['AFN']);
});

it('orders the picker afghani first', function () {
    currencySeed($this);

    expect(Currency::selectable()->pluck('code')->all())->toBe(['AFN', 'USD']);
});
