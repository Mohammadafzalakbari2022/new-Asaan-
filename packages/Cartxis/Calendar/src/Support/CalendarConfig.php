<?php

namespace Cartxis\Calendar\Support;

use Illuminate\Container\Container;

/**
 * Reading config from a place that may not have config.
 *
 * ------------------------------------------------------------------------
 * WHY THIS EXISTS
 * ------------------------------------------------------------------------
 * The calendar engine is deliberately usable without Laravel. Three callers
 * need that:
 *
 *   - a plain unit test, running the engine with no application booted;
 *   - `php artisan tinker` and one-off console scripts, before config is
 *     necessarily readable;
 *   - the JavaScript-parity harness, which loads the engine straight out of the
 *     autoloader to compare it against the TypeScript twin.
 *
 * Calling Laravel's global config() helper from those places is not a
 * convenience -- the helper EXISTS (it is a function, and function_exists()
 * returns true) while the container binding underneath it does not. So the
 * guard has to test the binding, not the function.
 *
 * Everything the engine reads through here has a default that is also the
 * right answer, so an unavailable config file degrades to that answer rather
 * than to an exception. That matters: a date that cannot be shown because a
 * config file is missing is a worse failure than a date shown in the default
 * way, and the defaults are the store's real settings anyway.
 */
final class CalendarConfig
{
    /**
     * A config value, or $default if it cannot be read.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (! self::available()) {
            return $default;
        }

        $value = config($key);

        return $value === null ? $default : $value;
    }

    /**
     * A config value as an array, or [] if it cannot be read or is not an array.
     *
     * Used for the calendar package's own config, where every key is optional.
     *
     * @return array<array-key, mixed>
     */
    public static function array(string $key): array
    {
        $value = self::get($key, []);

        return is_array($value) ? $value : [];
    }

    /**
     * Is there a booted application with readable config?
     */
    public static function available(): bool
    {
        if (! function_exists('app')) {
            return false;
        }

        $container = Container::getInstance();

        return $container !== null && $container->bound('config');
    }

    /**
     * The presentation settings the browser has to mirror.
     *
     * The TypeScript engine and this one default to the same answers, but the
     * server's config is the single source of truth: change CALENDAR_NUMERALS
     * and the picker must follow without a second edit in JavaScript.
     *
     * Deliberately not the month and weekday tables. The table is static, it
     * comes from SolarHijriLocale, and shipping it on every page would be
     * bytes for nothing. Only the decisions that could differ go here.
     *
     * @return array<string, mixed>
     */
    public static function shared(): array
    {
        return [
            'primary' => self::get('calendar.primary', 'solar'),
            'secondary' => self::get('calendar.secondary', 'gregorian'),
            'numerals' => self::get('calendar.numerals', 'fa'),
            'secondaryNumerals' => self::get('calendar.secondary_numerals', 'latn'),
            'bracket' => self::get('calendar.bracket', '({secondary})'),
            'weekStartsOn' => (int) self::get('calendar.week_starts_on', 6),
            'minYear' => (int) self::get('calendar.validated_min_year', 1399),
            'maxYear' => (int) self::get('calendar.validated_max_year', 1500),
        ];
    }
}
