<?php

namespace Cartxis\Core\Support;

/**
 * The one place the store country is defined.
 *
 * This store only sells inside Afghanistan, so the country is never something
 * a shopper gets to choose. Every write path normalises through
 * self::normalise(), which means a form can drop the field, a mobile app can
 * stop sending it, and the row still lands with the right value.
 *
 * The database columns stay exactly as they are — they are NOT NULL — this
 * class only decides what is written into them.
 */
final class StoreCountry
{
    /**
     * ISO 3166-1 alpha-2 code. This is what the country columns store.
     */
    public const CODE = 'AF';

    /**
     * Display name. Settings such as store_country hold this.
     */
    public const NAME = 'Afghanistan';

    /**
     * The country code every address row gets.
     */
    public static function code(): string
    {
        return self::CODE;
    }

    /**
     * The country name shown in settings and read-only screens.
     */
    public static function name(): string
    {
        return self::NAME;
    }

    /**
     * The country code, always. Use this instead of trusting input.
     *
     * Accepts anything (null, 'US', 'Afghanistan', an array) and answers with
     * the store country, so a caller cannot accidentally pass a client value
     * through to the database.
     */
    public static function normalise(mixed $value = null): string
    {
        return self::CODE;
    }

    /**
     * True when the given value already names the store country.
     *
     * Used by callers that need to decide whether stored data has drifted, not
     * for writes — writes always go through self::normalise().
     */
    public static function matches(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $value = strtoupper(trim($value));

        return in_array($value, [self::CODE, strtoupper(self::NAME)], true);
    }
}