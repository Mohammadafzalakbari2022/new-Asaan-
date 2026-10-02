<?php

/**
 * Blade and PHP-callable shortcuts for the Solar Hijri engine.
 *
 * Loaded by CalendarServiceProvider. These exist so that a template can print
 * a date without importing four classes and remembering the option names:
 *
 *      {{ solar_hijri($order->created_at) }}
 *      {{ solar_hijri($order->created_at, 'en') }}
 *      {{ solar_hijri($order->created_at, 'fa', ['weekday' => true]) }}
 *
 * Everything they return is a STRING FOR A PERSON. None of it may be stored,
 * sorted or sent to an API -- see the SolarHijri class docblock. If a template
 * needs a sortable value, it wants the Gregorian column it already has.
 */

use Cartxis\Calendar\Support\CalendarConfig;
use Cartxis\Calendar\Support\SolarHijri;
use Cartxis\Calendar\Support\SolarHijriFormatter;
use Cartxis\Calendar\Support\SolarHijriLocale;

if (! function_exists('solar_hijri')) {
    /**
     * The Solar Hijri date with the Gregorian reference in brackets:
     * '۱۵ شهریور ۱۴۰۴ (6 September 2025)'.
     *
     * @param  mixed  $date  Any date-shaped value.
     * @param  string|null  $locale  'fa', 'fa_alt', 'ps' or 'en'.
     *                                Null uses the request's language.
     * @param  array  $options  See SolarHijriFormatter::format().
     */
    function solar_hijri(mixed $date, ?string $locale = null, array $options = []): string
    {
        return SolarHijriFormatter::format($date, $locale, $options);
    }
}

if (! function_exists('solar_hijri_solar')) {
    /**
     * The Solar Hijri date on its own: '۱۵ شهریور ۱۴۰۴'.
     */
    function solar_hijri_solar(mixed $date, ?string $locale = null, array $options = []): string
    {
        return SolarHijriFormatter::solar($date, $locale, $options);
    }
}

if (! function_exists('solar_hijri_gregorian')) {
    /**
     * The Gregorian date on its own: '6 September 2025'.
     *
     * For a column that must stay copy-pasteable for a customer who does not
     * read Solar Hijri -- an export, a customs form, an accounting file.
     */
    function solar_hijri_gregorian(mixed $date, ?string $locale = null, array $options = []): string
    {
        return SolarHijriFormatter::gregorian($date, $locale, $options);
    }
}

if (! function_exists('solar_hijri_parts')) {
    /**
     * Both halves of a date, separately, for a template that wants to style
     * them differently -- the Gregorian reference smaller and greyer, say.
     *
     * Returns ['primary' => '۱۵ شهریور ۱۴۰۴', 'secondary' => '6 September 2025'].
     *
     * @return array{primary: string, secondary: string|null}
     */
    function solar_hijri_parts(mixed $date, ?string $locale = null, array $options = []): array
    {
        // Same resolution the formatter uses, so solar_hijri_parts() can never
        // disagree with solar_hijri() about which language a bare date is in.
        $resolved = $locale ?: SolarHijriLocale::storeDefault();

        $primary = $options['primary'] ?? CalendarConfig::get('calendar.primary', SolarHijriFormatter::SOLAR);
        $secondary = $options['secondary'] ?? CalendarConfig::get('calendar.secondary', SolarHijriFormatter::GREGORIAN);

        return [
            'primary' => $primary === SolarHijriFormatter::GREGORIAN
                ? SolarHijriFormatter::gregorian($date, $resolved, $options)
                : SolarHijriFormatter::solar($date, $resolved, $options),
            'secondary' => $secondary === SolarHijriFormatter::NONE
                ? null
                : SolarHijriFormatter::format($date, $resolved, [
                    ...$options,
                    'primary' => $primary,
                    'secondary' => SolarHijriFormatter::NONE,
                ]),
        ];
    }
}

if (! function_exists('solar_hijri_json')) {
    /**
     * A date as JSON carrying BOTH calendars, for a Vue date input.
     *
     * The shape a picker needs: what to show the shopper, and what to send back
     * to the server so the value that gets stored is Gregorian.
     *
     * Returns ['year' => 1404, 'month' => 6, 'day' => 15, 'iso' => '1404-06-15',
     * 'gregorian' => '2025-09-06', 'weekday' => 6, 'text' => '۱۵ شهریور ۱۴۰۴ (6 September 2025)'].
     *
     * @return array<string, mixed>
     */
    function solar_hijri_json(mixed $date, ?string $locale = null): array
    {
        $solar = SolarHijri::fromGregorian($date);

        return [
            ...$solar->jsonSerialize(),
            'text' => SolarHijriFormatter::format($date, $locale),
        ];
    }
}
