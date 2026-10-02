<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Restrict an existing install to AFN + USD.
 *
 * An install that ran the old country-derived seeder has ~149 currency rows,
 * every one of them switched on at a rate of 1.0. That is not a cosmetic
 * problem: the old seeder marked USD as the default, so such a store has been
 * displaying and formatting afghani amounts with a dollar symbol.
 *
 * The other rows are DEACTIVATED, not deleted. Nothing in the schema points at
 * them today, but deleting rows is irreversible and an order placed years ago
 * should still be able to resolve the currency it was written in. is_active =
 * false takes them out of the storefront picker, the API and every
 * admin-editable listing, and leaves the row readable.
 *
 * Driver-independent on purpose: this is a plain UPDATE and a plain insert
 * against columns that already exist, which is the one shape that behaves
 * identically on MySQL, PostgreSQL and SQLite. No enum widening, no table
 * rebuild, so no per-driver branch and nothing that opts out of the
 * transaction wrapper.
 */
return new class extends Migration
{
    /**
     * The rate written for USD when the store has never had one. The owner
     * edits it in admin settings ("1 USD = ? AFN"); re-running this migration
     * must not stomp a rate they have since corrected, so it is only ever
     * written on insert.
     */
    private const DEFAULT_USD_TO_AFN = 71.0;

    public function up(): void
    {
        if (! Schema::hasTable('currencies')) {
            return;
        }

        $supported = ['AFN', 'USD'];

        // Anything not on the list stops being offered. Still on disk.
        DB::table('currencies')
            ->whereNotIn('code', $supported)
            ->update(['is_active' => false, 'updated_at' => now()]);

        // The base is the default, so clear every other flag first: the model
        // normally enforces one default, but this writes through the query
        // builder and a legacy install may already have two.
        DB::table('currencies')->update(['is_default' => false]);

        $this->ensureCurrency([
            'code' => 'AFN',
            'name' => 'Afghan Afghani',
            'symbol' => "\u{060B}",
            'symbol_position' => 'before',
            'decimal_places' => 0,
            'exchange_rate' => 1.0,
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->ensureCurrency([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'symbol_position' => 'before',
            'decimal_places' => 2,
            'exchange_rate' => self::DEFAULT_USD_TO_AFN,
            'is_default' => false,
            'is_active' => true,
            'sort_order' => 2,
        ]);
    }

    /**
     * Make sure one of the two supported currencies exists and is correct.
     *
     * On UPDATE only the fields that must be true regardless of what the row
     * says: the afghani is the default and shows no decimals, the dollar
     * shows two. The exchange_rate is deliberately NOT in the update list --
     * a store that already has a real usd_to_afn keeps it, because silently
     * resetting the rate would change every converted price on the site.
     */
    private function ensureCurrency(array $attributes): void
    {
        $code = $attributes['code'];
        $now = now();

        $exists = DB::table('currencies')->where('code', $code)->exists();

        if ($exists) {
            $fix = array_intersect_key($attributes, array_flip([
                'name',
                'symbol',
                'symbol_position',
                'decimal_places',
                'is_default',
                'is_active',
                'sort_order',
            ]));

            $fix['updated_at'] = $now;

            DB::table('currencies')->where('code', $code)->update($fix);

            return;
        }

        DB::table('currencies')->insert($attributes + [
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Not reverted.
     *
     * Re-enabling 147 currencies would be an unrequested change to a live
     * store, and the original exchange rates behind them were 1.0 -- the fake
     * values this migration exists to retire. Reverting would restore a broken
     * state that the owner explicitly asked to leave behind.
     */
    public function down(): void
    {
        //
    }
};
