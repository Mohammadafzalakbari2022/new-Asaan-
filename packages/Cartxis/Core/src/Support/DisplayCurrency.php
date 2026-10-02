<?php

namespace Cartxis\Core\Support;

use Cartxis\Core\Models\Currency;

/**
 * Which currency a shopper is LOOKING at, and the one place a price is
 * converted for them.
 *
 * READ THIS BEFORE CALLING convert()
 * -----------------------------------
 * Money in this store is stored in AFN. That is the canonical figure and the
 * one that gets charged, refunded and reported. The shopper's chosen currency
 * only changes what the label on screen says.
 *
 * So convert() is DISPLAY ONLY. It produces a number to show a person, and
 * that number must never be written to the database, added to an order total,
 * or handed to a payment gateway. A converted figure that gets stored is a
 * figure that becomes wrong the moment the owner edits the rate -- and it
 * disagrees with the AFN figure it came from, which is worse than being simply
 * stale.
 *
 * A card gateway settles in USD and is handed a converted amount because that
 * is genuinely the currency its own API demands. That is a settlement concern
 * (see GatewayCurrency), not a storage concern.
 *
 * The shopper's choice lives in the session under self::SESSION_KEY. Anything
 * that is not one of the two supported codes is ignored, so a stale session
 * from before the store was restricted, or a hand-crafted cookie, falls back
 * to AFN instead of rendering an unavailable currency.
 */
final class DisplayCurrency
{
    /**
     * Session key holding the shopper's chosen currency code.
     */
    public const SESSION_KEY = 'display_currency';

    /**
     * Resolve the currency a shopper is viewing.
     *
     * Always returns a currency row. Falls back to the base currency (AFN) if
     * the session holds nothing usable, or names a row that has since been
     * switched off, so formatting never depends on a null check being
     * remembered by every caller.
     */
    public static function resolve(?string $code = null): Currency
    {
        $code = $code ?? self::requestedCode();

        $currency = Currency::isSupportedCode($code)
            ? Currency::getByCode((string) $code)
            : null;

        return $currency?->is_active === false
            ? (Currency::getDefault() ?? $currency)
            : ($currency ?? Currency::getDefault() ?? self::fallback());
    }

    /**
     * The code the shopper asked for, straight out of the session, unchecked.
     *
     * Deliberately not gated on the session being "started". The store flips
     * that flag off again the moment the response is sent, while keeping the
     * attributes in memory -- so a started-check makes this return null for a
     * session that plainly holds a choice, and quietly drops every shopper back
     * to afghani. Reading the value is enough: a session that was never loaded
     * has nothing in it, and get() returns null.
     */
    public static function requestedCode(): ?string
    {
        if (! app()->bound('session')) {
            return null;
        }

        $code = app('session')->get(self::SESSION_KEY);

        return is_string($code) ? strtoupper(trim($code)) : null;
    }

    /**
     * Remember the shopper's choice for this session.
     *
     * Only ever called with a validated code; a value outside the supported
     * list is ignored so the session cannot be pushed into a state no page can
     * render.
     */
    public static function remember(string $code): void
    {
        if (! Currency::isSupportedCode($code)) {
            return;
        }

        $currency = Currency::getByCode(strtoupper(trim($code)));

        if (! $currency || ! $currency->is_active) {
            return;
        }

        app('session')->put(self::SESSION_KEY, $currency->code);
    }

    /**
     * Convert a canonical AFN amount into a display currency.
     *
     * PRESENTATION ONLY -- see the class docblock. The amount comes in as the
     * stored AFN figure and comes out as a number to print, rounded to the
     * display currency's decimals so a screen never shows 6.9141... USD.
     */
    public static function convert(float $canonicalAfnAmount, ?string $displayCode = null): float
    {
        $target = self::resolve($displayCode);

        $converted = $target->convert($canonicalAfnAmount);

        return round($converted, $target->displayDecimals());
    }

    /**
     * Convert and format in one step -- the call templates and email/PDF
     * helpers want, so no screen has to remember which order the two go in.
     */
    public static function format(float $canonicalAfnAmount, ?string $displayCode = null): string
    {
        $target = self::resolve($displayCode);

        return $target->formatAmount(self::convert($canonicalAfnAmount, $displayCode));
    }

    /**
     * An AFN row to use when the currencies table cannot answer.
     *
     * Never persisted and never saved -- it exists so a partially seeded
     * database renders AFN instead of throwing inside a layout.
     */
    private static function fallback(): Currency
    {
        $currency = new Currency([
            'code' => Currency::BASE_CODE,
            'name' => 'Afghan Afghani',
            'symbol' => "\u{060B}",
            'symbol_position' => 'before',
            'decimal_places' => 0,
            'exchange_rate' => 1.0,
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $currency->id = 0;
        $currency->exists = false;

        return $currency;
    }
}
