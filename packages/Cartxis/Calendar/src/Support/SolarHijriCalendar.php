<?php

namespace Cartxis\Calendar\Support;

use DateTimeInterface;

/**
 * A whole Solar Hijri month, laid out in weeks, ready for a date picker.
 *
 * Every date in this store has two faces: what a person reads (Solar Hijri,
 * the primary) and what the machine keeps (Gregorian). A date picker is the
 * one place that has to hold both at once -- the shopper picks a Solar Hijri
 * day, and the value that reaches the server and ends up in the database has
 * to be Gregorian.
 *
 * So every cell here carries BOTH, plus the weekday index, plus the month's
 * name. Nothing about the Gregorian side leaks into what the shopper sees; the
 * browser just carries the Gregorian ISO string through untouched.
 *
 * Weeks start on Saturday, because that is where the Afghan week does. In an
 * RTL layout that also puts the first column on the right, which is where a
 * Dari or Pashto reader expects Sunday to be. One ordering for everyone -- no
 * per-locale week start to keep in sync.
 *
 * The leading and trailing days come from the neighbouring Solar Hijri months
 * and are marked 'inMonth' => false, so a grid can be drawn with them greyed
 * out without the caller having to work out which ones they are.
 */
final class SolarHijriCalendar
{
    /**
     * Weekday index that starts a week: 6 = Saturday.
     *
     * Weekday indices run 0 (Sunday) to 6 (Saturday) throughout the package.
     */
    public const WEEK_STARTS_ON = 6;

    /**
     * Build one month as a grid of weeks.
     *
     * @return array{
     *     year: int,
     *     month: int,
     *     isLeapYear: bool,
     *     daysInMonth: int,
     *     monthName: string,
     *     weekdays: list<string>,
     *     weeks: list<list<array<string, mixed>>>
     * }
     */
    public static function month(int $jalaliYear, int $jalaliMonth, ?string $locale = null): array
    {
        $locale = SolarHijriLocale::normalise($locale);

        $first = new SolarHijriDate($jalaliYear, $jalaliMonth, 1);
        $daysInMonth = $first->daysInMonth();

        // Blank cells in front of the 1st so the 1st lands under its weekday.
        $leading = ($first->weekday() - self::WEEK_STARTS_ON + 7) % 7;

        $cells = [];

        for ($offset = -$leading; $offset < $daysInMonth; $offset++) {
            $day = $offset + 1;
            $date = SolarHijri::addDays($first->toGregorianIso(), $offset);

            $cells[] = [
                'day' => $date->day,
                'month' => $date->month,
                'year' => $date->year,
                'iso' => $date->toSolarIso(),
                'gregorian' => $date->toGregorianIso(),
                'weekday' => $date->weekday(),
                'inMonth' => $offset >= 0 && $offset < $daysInMonth,
            ];
        }

        // Pad the end so the last week is a full row. The filler days are real
        // dates from the next month, not blanks, so hovering one still shows a
        // real day rather than nothing.
        while (count($cells) % 7 !== 0) {
            $date = SolarHijri::addDays($first->toGregorianIso(), count($cells));

            $cells[] = [
                'day' => $date->day,
                'month' => $date->month,
                'year' => $date->year,
                'iso' => $date->toSolarIso(),
                'gregorian' => $date->toGregorianIso(),
                'weekday' => $date->weekday(),
                'inMonth' => false,
            ];
        }

        return [
            'year' => $jalaliYear,
            'month' => $jalaliMonth,
            'isLeapYear' => SolarHijri::isLeapYear($jalaliYear),
            'daysInMonth' => $daysInMonth,
            'monthName' => SolarHijriLocale::monthName($jalaliMonth, $locale),
            'weekdays' => array_map(
                static fn (int $offset): string => SolarHijriLocale::weekdayName(
                    (self::WEEK_STARTS_ON + $offset) % 7,
                    $locale,
                    true,
                ),
                range(0, 6),
            ),
            'weeks' => array_chunk($cells, 7),
        ];
    }

    /**
     * A whole year: twelve months, ready to page through.
     *
     * @return array{year: int, isLeapYear: bool, months: list<array<string, mixed>>}
     */
    public static function year(int $jalaliYear, ?string $locale = null): array
    {
        $months = [];

        for ($month = 1; $month <= 12; $month++) {
            $months[] = self::month($jalaliYear, $month, $locale);
        }

        return [
            'year' => $jalaliYear,
            'isLeapYear' => SolarHijri::isLeapYear($jalaliYear),
            'months' => $months,
        ];
    }

    /**
     * The month a date belongs to, as a grid -- "open the calendar on this
     * date". What a date input calls when it is given an existing value.
     *
     * @return array<string, mixed>
     */
    public static function monthFor(DateTimeInterface|string|int $date, ?string $locale = null): array
    {
        $solar = SolarHijri::fromGregorian($date);

        return self::month($solar->year, $solar->month, $locale);
    }
}
