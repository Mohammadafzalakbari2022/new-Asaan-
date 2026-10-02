<?php

/**
 * How money is printed.
 *
 * The rule that matters: an afghani amount has no decimals. The afghani is not
 * split into hundredths in practice -- you pay 500 afghani, not 500.00 -- so a
 * price reading "؋500.00" is telling a customer something that is not true, and
 * a payment gateway that charges in afghani has no minor unit to charge in.
 *
 * These tests pin the printing, not just the stored column, because the two
 * are allowed to disagree in exactly one direction: the column may say 2, and
 * the afghani must still print with none.
 */

declare(strict_types=1);

require_once __DIR__ . '/CurrencyTestHelpers.php';

use Cartxis\Core\Models\Currency;
use Cartxis\Core\Support\DisplayCurrency;

beforeEach(function () {
    currencySeed($this);
});

// ---------------------------------------------------------------------------
// The afghani has no decimals
// ---------------------------------------------------------------------------

it('prints an afghani amount with no decimals at all', function () {
    $afn = currencyRow('AFN');

    expect($afn->formatAmount(500))->toBe("\u{060B}500");
    expect($afn->formatAmount(0))->toBe("\u{060B}0");
    expect($afn->formatAmount(1234))->toBe("\u{060B}1,234");
});

it('never prints two decimals on the afghani, even for a stored fraction', function () {
    // 499.60 of stock value is 500 afghani to anybody shopping, and 499.60 is
    // not a price anyone in Afghanistan would read or pay.
    expect(currencyRow('AFN')->formatAmount(499.6))->toBe("\u{060B}500");
});

it('keeps the afghani at zero decimals even if the column is edited to two', function () {
    // A hand-edited row, an import, a bad migration. The afghani is still the
    // afghani, so the display rule is not something the column gets a vote on.
    currencyRow('AFN')->update(['decimal_places' => 2]);

    $afn = currencyRow('AFN');

    expect($afn->decimal_places)->toBe(2);
    expect($afn->displayDecimals())->toBe(0);
    expect($afn->formatAmount(500))->toBe("\u{060B}500");
    expect($afn->formatAmount(500.25))->toBe("\u{060B}500");
});

it('does not truncate the afghani to a negative zero', function () {
    // round() in PHP can hand back -0.0, and number_format then prints "-0".
    expect(currencyRow('AFN')->formatAmount(-0.4))->toBe("\u{060B}0");
});

it('formats a null amount without falling over', function () {
    expect(currencyRow('AFN')->formatAmount(null))->toBe("\u{060B}0");
});

// ---------------------------------------------------------------------------
// The dollar keeps two
// ---------------------------------------------------------------------------

it('prints a dollar amount with two decimals', function () {
    $usd = currencyRow('USD');

    expect($usd->formatAmount(12))->toBe('$12.00');
    expect($usd->formatAmount(12.5))->toBe('$12.50');
    expect($usd->formatAmount(1234.5))->toBe('$1,234.50');
    expect($usd->formatAmount(0))->toBe('$0.00');
});

it('obeys a dollar row that was given a different number of decimals', function () {
    // The pinning is the afghani's alone. Anything else prints what its column
    // says, because that is the owner editing a setting and expecting it to
    // take effect.
    currencyRow('USD')->update(['decimal_places' => 0]);

    expect(currencyRow('USD')->formatAmount(12.5))->toBe('$13');
});

// ---------------------------------------------------------------------------
// Which side of the number the symbol goes on
// ---------------------------------------------------------------------------

it('puts the symbol before the amount', function () {
    currencyRow('AFN')->update(['symbol_position' => 'before']);

    expect(currencyRow('AFN')->formatAmount(1250))->toBe("\u{060B}1,250");
});

it('puts the symbol after the amount', function () {
    // A real convention, not a hypothetical: "1250 ؋" is how the afghani is
    // written in Dari and Pashto text, and a store serving those shoppers needs
    // it to be possible.
    currencyRow('AFN')->update(['symbol_position' => 'after']);

    expect(currencyRow('AFN')->formatAmount(1250))->toBe("1,250\u{060B}");
});

it('applies the after position to the dollar too, decimals and all', function () {
    currencyRow('USD')->update(['symbol_position' => 'after']);

    expect(currencyRow('USD')->formatAmount(12.5))->toBe('12.50$');
});

it('defaults to before rather than printing a bare number', function () {
    // The column is a constrained enum, so an unrecognised value can only
    // reach the formatter from a bad import or a hand-built row. It still must
    // not print "500" with no currency at all, which is how a price stops
    // being a price.
    $afn = new Currency([
        'code' => 'AFN',
        'name' => 'Afghan Afghani',
        'symbol' => "\u{060B}",
        'symbol_position' => 'left-ish',
        'decimal_places' => 0,
    ]);

    expect($afn->formatAmount(500))->toBe("\u{060B}500");
});

// ---------------------------------------------------------------------------
// The one call a template makes
// ---------------------------------------------------------------------------

it('converts and formats in one call for the afghani', function () {
    expect(DisplayCurrency::format(1250, 'AFN'))->toBe("\u{060B}1,250");
});

it('converts and formats in one call for the dollar', function () {
    setExchangeRate(50.0);

    // 1250 AFN at "1 USD = 50 AFN" is 25 dollars.
    expect(DisplayCurrency::format(1250, 'USD'))->toBe('$25.00');
});

it('exposes the same helpers on the model', function () {
    setExchangeRate(25.0);

    expect(Currency::formatForDisplay(2500, 'USD'))->toBe('$100.00');
    expect(Currency::formatForDisplay(2500, 'AFN'))->toBe("\u{060B}2,500");
});
