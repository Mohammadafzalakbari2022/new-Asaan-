<?php

namespace Cartxis\Referral\Support;

use Cartxis\Core\Models\Currency;
use Throwable;

/**
 * Plain-text money for the few places that build a sentence instead of handing a
 * number to the frontend.
 *
 * The store's currency is a row in the `currencies` table, reached through
 * Currency::getDefault(). There is no config/currency.php in this project, so
 * config('currency.symbol') always returned its fallback and the referral copy
 * said "AFN" on a shop priced in anything else. Correctness lives in
 * Currency::format(); this class only supplies the "what if there is no store
 * currency yet" case that the model does not cover.
 *
 * Anything user facing that is rendered by Vue should use the useCurrency()
 * composable instead, which reads the shared Inertia prop.
 */
class Money
{
    /**
     * The store's currency symbol, or an empty string if the store has not set
     * one up yet.
     */
    public static function symbol(): string
    {
        return trim((string) (self::currency()?->symbol ?? ''));
    }

    public static function code(): string
    {
        return trim((string) (self::currency()?->code ?? ''));
    }

    /**
     * A plain string like "AFN 500" for use inside a sentence, using the store's
     * own symbol and decimal places.
     *
     * When the store has no currency configured yet, the bare number is
     * returned. Showing a number is recoverable; showing the wrong currency is
     * a promise the shop cannot keep.
     */
    public static function inSentence(float $amount): string
    {
        $currency = self::currency();

        return $currency
            ? $currency->format($amount)
            : number_format($amount, 0);
    }

    protected static function currency(): ?Currency
    {
        try {
            return Currency::getDefault();
        } catch (Throwable) {
            // During install, or on a store whose tables are not ready, there is
            // no currency row to read. Null is the correct answer: the caller
            // shows a bare number rather than an invented symbol.
            return null;
        }
    }
}
