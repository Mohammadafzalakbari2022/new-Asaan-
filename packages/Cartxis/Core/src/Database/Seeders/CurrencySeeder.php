<?php

declare(strict_types=1);

namespace Cartxis\Core\Database\Seeders;

use Cartxis\Core\Models\Currency;
use Illuminate\Database\Seeder;

/**
 * The store's two currencies.
 *
 * This used to derive 149 currencies from the countries table, all switched on
 * at a rate of exactly 1.0, which meant every one of them claimed a US dollar
 * was worth a rupee. Now it seeds only what the store actually trades in.
 *
 * AFN is the base: prices are stored in it, and it shows no decimals because
 * there is no practical afghani subunit. USD is the optional display currency,
 * and its exchange_rate is the usd_to_afn rate -- "1 USD = ? AFN" -- which the
 * owner edits in admin settings.
 *
 * upsert() rather than insert() so re-seeding an existing install fixes the
 * two supported rows in place and leaves their ids alone.
 */
class CurrencySeeder extends Seeder
{
    /**
     * The rows, in the order they appear in the admin list and the picker.
     *
     * The keys map one-to-one onto the columns on the currencies table:
     * code, name, symbol, symbol_position, decimal_places, exchange_rate,
     * is_default, is_active, sort_order.
     *
     * @var list<array<string, mixed>>
     */
    private const CURRENCIES = [
        [
            'code' => Currency::BASE_CODE,
            'name' => 'Afghan Afghani',
            'symbol' => "\u{060B}",
            'symbol_position' => 'before',
            // No decimals. "؋500", never "؋500.00".
            'decimal_places' => 0,
            // The base is always worth one of itself.
            'exchange_rate' => 1.0,
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 1,
        ],
        [
            'code' => Currency::OPTIONAL_CODE,
            'name' => 'US Dollar',
            'symbol' => '$',
            'symbol_position' => 'before',
            'decimal_places' => 2,
            // "1 USD = 71 AFN". The owner edits this; see the admin settings
            // label built by Currency::exchangeRateLabel().
            'exchange_rate' => Currency::DEFAULT_USD_TO_AFN,
            'is_default' => false,
            'is_active' => true,
            'sort_order' => 2,
        ],
    ];

    public function run(): void
    {
        $now = now();

        $rows = array_map(static function (array $currency) use ($now): array {
            return $currency + [
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, self::CURRENCIES);

        // is_default and exchange_rate are left OUT of the update list on
        // purpose.
        //
        // is_default goes through the model's saving hook, which is what keeps a
        // single default; writing it straight from the query builder would
        // bypass that. It is re-asserted on the row below instead.
        //
        // exchange_rate is the owner's number, typed into admin settings as
        // "1 USD = ? AFN" and corrected as the market moves. Re-seeding a live
        // store must not put it back to the seeded 71: that silently changes
        // the price of every converted figure on the site back to a figure the
        // owner has already rejected. It is still written on INSERT, so a
        // first-time seed gets a rate and a rate-less store keeps its own.
        Currency::query()->upsert(
            $rows,
            ['code'],
            ['name', 'symbol', 'symbol_position', 'decimal_places', 'is_active', 'sort_order', 'updated_at']
        );

        $base = Currency::query()->where('code', Currency::BASE_CODE)->first();

        // Unconditional, not only when the flag was wrong. The model's saving
        // hook is what guarantees a single default, and it only runs when a row
        // is saved -- so on a re-seed of a store that had two rows flagged
        // default, skipping the save would leave both flagged. Every row is
        // re-asserted, not just the one that looked wrong.
        $base?->update(['is_default' => true]);
    }
}
