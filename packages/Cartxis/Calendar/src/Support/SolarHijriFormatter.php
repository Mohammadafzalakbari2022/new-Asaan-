<?php

namespace Cartxis\Calendar\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Turning a date into the string a person actually reads.
 *
 * ------------------------------------------------------------------------
 * THE ONE DECISION THIS CLASS ENCODES
 * ------------------------------------------------------------------------
 * A Solar Hijri store shows the Solar Hijri date FIRST and the Gregorian date
 * second, in brackets. Always. That is the owner's decision and it is not up
 * for renegotiation at each call site, which is why it lives in config rather
 * than in 29 date inputs and 45 Vue files:
 *
 *      ۱۵ شهریور ۱۴۰۴ (6 September 2025)
 *
 * Primary   the Solar Hijri date, in the reader's language and script.
 * Secondary the Gregorian date, small, in brackets, never leading.
 *
 * The bracketing is deliberate. It tells a reader who knows both calendars
 * that these are the same day described twice, not two different dates, and it
 * keeps the store legible to anyone -- a card statement, a customs form, a
 * message to an accountant in another country -- who does not read Solar Hijri
 * at all. That person can still do their job, and no screen has to grow a
 * toggle to let them.
 *
 * The two halves also differ in their DIGITS on purpose. The Solar Hijri half
 * is the one in the reader's own script, so it is set in Persian numerals. The
 * Gregorian half is the reference, and a reference is most useful when it can
 * be copied straight into a bank form or a spreadsheet -- so it stays Latin.
 * Both are configurable.
 *
 * ------------------------------------------------------------------------
 * WHAT THIS IS NOT
 * ------------------------------------------------------------------------
 * Nothing here returns a value meant to be stored, sorted or sent to an API.
 * The output of format() is text for a person -- see the SolarHijri class
 * docblock. If you want something for the database, you want the Gregorian
 * value you already had.
 */
final class SolarHijriFormatter
{
    /** The Solar Hijri date leads. The store's default. */
    public const SOLAR = 'solar';

    /** The Gregorian date leads. For exports an outside system reads. */
    public const GREGORIAN = 'gregorian';

    /** Show no second calendar at all -- just the primary. */
    public const NONE = 'none';

    /**
     * Format a date for a person.
     *
     * @param  DateTimeInterface|string|int|null  $date  Any date-shaped value.
     *                                                  Null means "now".
     * @param  string|null  $locale  'fa' (the store default), 'fa_alt', 'ps'
     *                                or 'en'. Null means the app's current
     *                                locale, so an English reader gets English
     *                                month names without any call site knowing.
     * @param  array  $options  Any of these, each defaulting to config:
     *        primary            self::SOLAR | self::GREGORIAN
     *        secondary          self::GREGORIAN | self::SOLAR | self::NONE
     *        numerals           'fa' | 'arab' | 'latn'     -- the primary
     *        secondaryNumerals  'latn' | 'fa' | 'arab'     -- the secondary
     *        weekday            bool -- lead with the weekday name
     *        short              bool -- '۱۴۰۴/۰۶/۱۵' instead of words
     *        bracket            template with a {secondary} placeholder,
     *                           default '({secondary})'
     */
    public static function format(
        DateTimeInterface|string|int|null $date = null,
        ?string $locale = null,
        array $options = [],
    ): string {
        $options = self::resolveOptions($options);

        $date ??= self::now();
        $solar = SolarHijri::fromGregorian($date);
        $locale ??= self::appLocale();

        $primary = $options['primary'] === self::GREGORIAN
            ? self::renderGregorian($solar, $locale, $options, $options['numerals'])
            : self::renderSolar($solar, $locale, $options);

        $secondary = match ($options['secondary']) {
            self::NONE => null,
            self::SOLAR => self::renderSolar($solar, $locale, [
                ...$options,
                'numerals' => $options['secondaryNumerals'],
            ]),
            default => self::renderGregorian($solar, $locale, $options, $options['secondaryNumerals']),
        };

        if ($secondary === null || $secondary === '') {
            return $primary;
        }

        return $primary.' '.str_replace('{secondary}', $secondary, $options['bracket']);
    }

    /**
     * Just the Solar Hijri half: '۱۵ شهریور ۱۴۰۴'.
     *
     * For a table column where every row already shows the year, or a tooltip
     * with no room for two dates.
     */
    public static function solar(
        DateTimeInterface|string|int|null $date = null,
        ?string $locale = null,
        array $options = [],
    ): string {
        $options['secondary'] = self::NONE;

        return self::format($date, $locale, $options);
    }

    /**
     * Just the Gregorian half: '6 September 2025'.
     *
     * For a column that must stay copy-pasteable for a customer who does not
     * read Solar Hijri at all -- an order export, a customs invoice, an
     * accounting export.
     *
     * The secondary calendar is switched off unless the caller asks for one, so
     * this cannot print the same date twice.
     */
    public static function gregorian(
        DateTimeInterface|string|int|null $date = null,
        ?string $locale = null,
        array $options = [],
    ): string {
        $options['primary'] = self::GREGORIAN;
        $options['secondary'] ??= self::NONE;

        return self::format($date, $locale, $options);
    }

    /**
     * In words, with the weekday:
     * 'شنبه، ۱۵ شهریور ۱۴۰۴ (Saturday, 6 September 2025)'.
     *
     * For emails and PDFs, where there is room and a date in a sentence
     * usually deserves a weekday.
     */
    public static function long(
        DateTimeInterface|string|int|null $date = null,
        ?string $locale = null,
    ): string {
        return self::format($date, $locale, ['weekday' => true]);
    }

    /**
     * In numerals only: '۱۴۰۴/۰۶/۱۵ (2025/09/06)'.
     *
     * For a table cell, where width is the problem. Still both calendars --
     * only the words are gone.
     */
    public static function short(
        DateTimeInterface|string|int|null $date = null,
        ?string $locale = null,
        array $options = [],
    ): string {
        $options['short'] = true;

        return self::format($date, $locale, $options);
    }

    /**
     * The Solar Hijri half of the string.
     */
    private static function renderSolar(SolarHijriDate $solar, string $locale, array $options): string
    {
        $dateParts = $options['short']
            ? [PersianDigits::to(
                sprintf('%04d/%02d/%02d', $solar->year, $solar->month, $solar->day),
                $options['numerals'],
            )]
            : [
                PersianDigits::to((string) $solar->day, $options['numerals']),
                SolarHijriLocale::monthName($solar->month, $locale),
                PersianDigits::to((string) $solar->year, $options['numerals']),
            ];

        // Day, month and year read as one run: separated by spaces. A weekday
        // in front of them is a separate thought, so it gets a comma -- and in
        // the RTL scripts a comma is what keeps the reader from parsing the
        // weekday as part of the date.
        $lead = $options['weekday']
            ? SolarHijriLocale::weekdayName($solar->weekday(), $locale).'، '
            : '';

        return $lead.implode(' ', $dateParts);
    }

    /**
     * The Gregorian half of the string.
     *
     * PHP's own date() letters are used, so this half is spelled identically
     * whatever the process locale happens to be: 'F' is always the English
     * month name, which is exactly what a bracketed reference needs to be.
     *
     * The Gregorian date is always read back off the Solar Hijri date rather
     * than off the input, so the two halves of the string cannot describe
     * different days even if the caller passed something loose.
     */
    private static function renderGregorian(
        SolarHijriDate $solar,
        string $locale,
        array $options,
        string $numerals,
    ): string {
        $pattern = $options['short'] ? 'Y/m/d' : 'j F Y';

        if ($options['weekday']) {
            $pattern = 'l, '.$pattern;
        }

        return PersianDigits::to($solar->toGregorian()->format($pattern), $numerals);
    }

    /**
     * Fill in every option from config, so no call site has to.
     */
    private static function resolveOptions(array $options): array
    {
        $config = CalendarConfig::array('calendar');

        return [
            'primary' => $options['primary'] ?? $config['primary'] ?? self::SOLAR,
            'secondary' => $options['secondary'] ?? $config['secondary'] ?? self::GREGORIAN,
            'numerals' => $options['numerals'] ?? $config['numerals'] ?? 'fa',
            'secondaryNumerals' => $options['secondaryNumerals']
                ?? $config['secondary_numerals']
                ?? 'latn',
            'weekday' => (bool) ($options['weekday'] ?? false),
            'short' => (bool) ($options['short'] ?? false),
            'bracket' => (string) ($options['bracket'] ?? $config['bracket'] ?? '({secondary})'),
        ];
    }

    /**
     * "Now", for a null date, in the app's timezone.
     *
     * A store in Afghanistan runs on Asia/Kabul. Asking for "today" in the
     * server's own zone would show the wrong day for a few hours around
     * midnight, which on a date that only changes once a year is exactly the
     * wrong place to be wrong.
     */
    private static function now(): DateTimeInterface
    {
        $timezone = CalendarConfig::get('app.timezone');

        return Carbon::now(is_string($timezone) && $timezone !== '' ? $timezone : null);
    }

    /**
     * The reader's locale, for a null locale.
     *
     * Deliberately not a hardcoded 'fa'. A request that has chosen English --
     * the storefront switcher, the mobile app -- must get English month names,
     * not Dari ones. Only a caller that has chosen nothing gets the store's own
     * default, which is what SolarHijriLocale::storeDefault() reads.
     */
    private static function appLocale(): string
    {
        return SolarHijriLocale::storeDefault();
    }
}
