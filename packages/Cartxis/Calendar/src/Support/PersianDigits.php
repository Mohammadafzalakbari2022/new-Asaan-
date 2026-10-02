<?php

namespace Cartxis\Calendar\Support;

/**
 * Turning Western digits into the ones Dari and Pashto readers expect.
 *
 * Two sets, and the difference matters:
 *
 *   'arab'  U+0660-0669 ARABIC-INDIC. What Arabic script uses, and what
 *           computer fonts tend to render most faithfully.
 *   'fa'    U+06F0-06F9 EXTENDED ARABIC-INDIC. The Persian set. The digits are
 *           visibly different -- ۴ has a different shape from ٤ -- and this is
 *           the one Iranian and Afghan Persian text uses.
 *
 * 'fa' is the default here because that is what a Dari date reads as.
 *
 * Decided once, globally, in config('calendar.numerals'), because an invoice
 * that says ۱۵ سنبله ۱۴۰۴ while the order email it was emailed as said
 * 15 Shahrivar 1404 is the kind of inconsistency a customer notices
 * immediately. Both sides read the same config.
 *
 * Note what is NOT converted: only digits. An order number, a phone number, a
 * tracking code or an email address is untouched unless you send it through
 * digits() on purpose -- a digit-shaped ID that changes script is no longer
 * searchable.
 */
final class PersianDigits
{
    /**
     * Latin '0'-'9' indexed to their Persian counterparts.
     */
    private const PERSIAN = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    /**
     * Latin '0'-'9' indexed to their Arabic-Indic counterparts.
     */
    private const ARABIC = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    /**
     * Convert the digits in a string to the requested numeral set.
     *
     * Safe on a whole formatted date: it walks the string and swaps only
     * characters in the 0x30-0x39 range, so the month name that happens to
     * sit between the digits is left exactly as it was.
     */
    public static function to(string $value, ?string $numerals = null): string
    {
        $numerals = self::resolve($numerals);

        if ($numerals === 'latn' || ! preg_match('/[0-9]/', $value)) {
            return $value;
        }

        $map = $numerals === 'arab' ? self::ARABIC : self::PERSIAN;

        return strtr($value, array_combine(
            array_map('strval', range(0, 9)),
            $map,
        ));
    }

    /**
     * The opposite: back to Latin digits, for anything a machine reads.
     *
     * Needed when a Solar Hijri date picked in the browser comes back in
     * Persian digits, or when someone types ۱۴۰۴ into a search box.
     */
    public static function from(string $value): string
    {
        $persianInverse = array_flip(self::PERSIAN);
        $arabicInverse = array_flip(self::ARABIC);

        $translated = strtr($value, array_merge(
            array_map('strval', $persianInverse),
            array_map('strval', $arabicInverse),
        ));

        return $translated;
    }

    /**
     * Convert an integer to its digits in the requested set.
     */
    public static function number(int|float $value, ?string $numerals = null): string
    {
        return self::to((string) $value, $numerals);
    }

    /**
     * The numeral set to use: the one asked for, or the configured default.
     *
     * An unrecognised set falls back to 'latn' rather than Persian, because
     * an unreadable date on an invoice is a worse outcome than a Latin one.
     */
    private static function resolve(?string $numerals): string
    {
        $numerals ??= (string) (CalendarConfig::get('calendar.numerals') ?: 'fa');

        return in_array($numerals, ['latn', 'fa', 'arab'], true) ? $numerals : 'latn';
    }
}
