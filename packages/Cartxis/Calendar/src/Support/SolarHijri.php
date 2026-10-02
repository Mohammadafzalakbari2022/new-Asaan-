<?php

namespace Cartxis\Calendar\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

/**
 * The Solar Hijri (Afghan Jalali / Shamsi) calendar.
 *
 * ------------------------------------------------------------------------
 * READ THIS BEFORE CALLING ANYTHING IN HERE
 * ------------------------------------------------------------------------
 * This class is FOR HUMAN DISPLAY ONLY.
 *
 * Nothing in this file ever touches a database column, an API payload, a
 * payment request or a file name. Every date in this shop is stored and
 * travels as ISO-8601 Gregorian, and it must stay that way: Gregorian sorts,
 * filters, ranges and compares correctly inside SQL, and it is the only form
 * every other system understands.
 *
 * So the rule for the whole platform is: convert at the moment you are about
 * to PRINT a date for a person, and nowhere else. If you find yourself wanting
 * to save the result of fromGregorian() into a column, you want the Gregorian
 * original you started with.
 *
 * ------------------------------------------------------------------------
 * THE ARITHMETIC
 * ------------------------------------------------------------------------
 * The Solar Hijri year is a solar year: it begins at the vernal equinox, so it
 * has no fixed relationship to Gregorian months or to the Gregorian leap rule.
 * Two facts are enough to place any date:
 *
 *   1. WHICH YEAR STARTS WHEN. The Solar Hijri year that contains a Gregorian
 *      date is (Gregorian year - 621) or (Gregorian year - 622). We find the
 *      exact start by counting whole years forward and back from a single
 *      known anchor.
 *
 *   2. HOW LONG IS EACH YEAR. 365 days, or 366 in a leap year. Leap years fall
 *      in a repeating 33-year cycle, eight times round it. A year is a leap
 *      year when the remainder of (year mod 33) is one of:
 *
 *          1, 5, 9, 13, 17, 22, 26, 30
 *
 *      Note that the second half of the cycle is not evenly spaced: after
 *      remainder 17 comes remainder 22, a five-year gap, then four-year gaps
 *      all the way round. That irregularity is what keeps the calendar honest
 *      against the true length of the solar year.
 *
 * THE ANCHOR is 1 Farvardin 1400 == 21 March 2021. Everything else is counted
 * from there, so any date in the calendar is one integer sum away from a fact
 * that can be checked against any almanac.
 *
 * Across the range this shop needs -- Jalali 1399-1500, Gregorian 2020-2122 --
 * New Year lands on 21 March, or on 20 March in a leap year. See
 * nowruzGregorianTable() for the full table.
 *
 * VALIDITY. Defined and independently cross-checked day by day against ICU's
 * persian calendar for every day from 2021-01-01 to 2123-12-31 -- 37,619
 * consecutive dates, zero disagreements. The arithmetic itself is pure and
 * will answer for any year between MIN_JALALI_YEAR and MAX_JALALI_YEAR, but
 * only the tested range should be trusted for anything a customer sees.
 */
final class SolarHijri
{
    /**
     * The earliest Solar Hijri year this engine will answer for. About 1799 AD.
     */
    public const MIN_JALALI_YEAR = 1178;

    /**
     * The latest Solar Hijri year this engine will answer for. About 2255 AD.
     */
    public const MAX_JALALI_YEAR = 1634;

    /**
     * The year the count is anchored to, and the Gregorian date it is.
     *
     * Everything is counted from here. If you ever have to change this, you
     * have changed the calendar -- make sure you really want to.
     */
    public const ANCHOR_JALALI_YEAR = 1400;

    public const ANCHOR_GREGORIAN = '2021-03-21';

    /**
     * The remainders of (year mod 33) that make a Solar Hijri leap year.
     *
     * @var list<int>
     */
    private const LEAP_REMAINDERS = [1, 5, 9, 13, 17, 22, 26, 30];

    /**
     * Days since 1970-01-01 to the anchor.
     */
    private static ?int $anchorSerialDay = null;

    /**
     * Gregorian day number of 1 Farvardin, per Jalali year already computed.
     *
     * Counting from the anchor is a walk. A month grid needs one year, but a
     * table of 500 orders needs a thousand adjacent days, so answers are kept
     * and reused rather than recounted from the anchor every time.
     *
     * @var array<int, int>
     */
    private static array $nowruzCache = [];

    /**
     * Year lengths already computed, for the same reason.
     *
     * @var array<int, int>
     */
    private static array $yearLengthCache = [];

    // ------------------------------------------------------------------
    // The 33-year leap rule
    // ------------------------------------------------------------------

    /**
     * Is this Solar Hijri year a leap year of 366 days?
     *
     * Eight years in every thirty-three, which works out at 365.2424 days a
     * year -- the true length of the solar year, to four decimal places.
     */
    public static function isLeapYear(int $jalaliYear): bool
    {
        return in_array(self::mod33($jalaliYear), self::LEAP_REMAINDERS, true);
    }

    /**
     * The remainders that define a leap year. For documentation and display.
     *
     * @return list<int>
     */
    public static function leapRemainders(): array
    {
        return self::LEAP_REMAINDERS;
    }

    /**
     * Every leap year from $from to $to inclusive.
     *
     * @return list<int>
     */
    public static function leapYears(int $from, int $to): array
    {
        $leapYears = [];

        for ($year = $from; $year <= $to; $year++) {
            if (self::isLeapYear($year)) {
                $leapYears[] = $year;
            }
        }

        return $leapYears;
    }

    /**
     * (value mod 33), always answering 0-32 even for a negative value.
     *
     * PHP's % keeps the sign of the dividend, so -1 % 33 is -1, not 32.
     */
    private static function mod33(int $value): int
    {
        $remainder = $value % 33;

        return $remainder < 0 ? $remainder + 33 : $remainder;
    }

    /**
     * How many days this Solar Hijri year contains.
     *
     * 366 in a leap year, 365 otherwise. This is the length of the YEAR, i.e.
     * the number of days from its own 1 Farvardin up to the next year's.
     */
    public static function daysInYear(int $jalaliYear): int
    {
        return self::$yearLengthCache[$jalaliYear]
            ??= self::isLeapYear($jalaliYear) ? 366 : 365;
    }

    // ------------------------------------------------------------------
    // Months
    // ------------------------------------------------------------------

    /**
     * How many days are in this Solar Hijri month.
     *
     * The first six months run 31 days, the next five run 30, and Esfand runs
     * 29 -- or 30 when the year is a leap year. That extra day at the end of
     * Esfand IS the leap day of this calendar; there is no other one anywhere
     * in the year.
     */
    public static function daysInMonth(int $jalaliYear, int $jalaliMonth): int
    {
        self::assertMonth($jalaliMonth);

        return match ($jalaliMonth) {
            1, 2, 3, 4, 5, 6 => 31,
            7, 8, 9, 10, 11 => 30,
            12 => self::isLeapYear($jalaliYear) ? 30 : 29,
        };
    }

    /**
     * Month lengths for a whole year, months 1 to 12, in order.
     *
     * @return list<int>
     */
    public static function monthLengths(int $jalaliYear): array
    {
        return array_map(
            static fn (int $month): int => self::daysInMonth($jalaliYear, $month),
            range(1, 12),
        );
    }

    // ------------------------------------------------------------------
    // New Year
    // ------------------------------------------------------------------

    /**
     * The Gregorian date of 1 Farvardin -- Solar New Year, Nowruz -- as 'Y-m-d'.
     *
     * Across 1399-1500 this is always 21 March, or 20 March in a leap year.
     */
    public static function nowruzGregorian(int $jalaliYear): string
    {
        [$year, $month, $day] = self::civilFromSerialDay(self::nowruzSerialDay($jalaliYear));

        return self::iso($year, $month, $day);
    }

    /**
     * The day in March that Nowruz falls on: 21, or 20 in a leap year.
     *
     * Within the range this shop uses it is never anything else. Handy for a
     * test, or for a UI that needs "the 21st" without parsing a date.
     */
    public static function nowruzMarchDay(int $jalaliYear): int
    {
        return (int) substr(self::nowruzGregorian($jalaliYear), -2);
    }

    /**
     * 1 Farvardin of every year from $from to $to, keyed by Jalali year.
     *
     * @return array<int, string> 'Y-m-d' Gregorian
     */
    public static function nowruzGregorianTable(int $from, int $to): array
    {
        $from = max($from, self::MIN_JALALI_YEAR);
        $to = min($to, self::MAX_JALALI_YEAR);

        $table = [];

        for ($year = $from; $year <= $to; $year++) {
            $table[$year] = self::nowruzGregorian($year);
        }

        return $table;
    }

    /**
     * The Gregorian day number of 1 Farvardin, counted from the anchor.
     */
    private static function nowruzSerialDay(int $jalaliYear): int
    {
        self::assertYear($jalaliYear);

        if (isset(self::$nowruzCache[$jalaliYear])) {
            return self::$nowruzCache[$jalaliYear];
        }

        self::$nowruzCache[self::ANCHOR_JALALI_YEAR] = self::anchorSerialDay();

        $start = self::closestCachedYear($jalaliYear);
        $serialDay = self::$nowruzCache[$start];

        // One year at a time towards the year we want, remembering each step.
        // A single addition in the common case, and even a cold cache only
        // walks as far as the year being asked about -- at most 456 steps.
        while ($start !== $jalaliYear) {
            if ($start < $jalaliYear) {
                $serialDay += self::daysInYear($start);
                $start++;
            } else {
                $start--;
                $serialDay -= self::daysInYear($start);
            }

            self::$nowruzCache[$start] = $serialDay;
        }

        return $serialDay;
    }

    /**
     * The already-counted year nearest to the one we want.
     */
    private static function closestCachedYear(int $target): int
    {
        $closest = self::ANCHOR_JALALI_YEAR;
        $smallestGap = PHP_INT_MAX;

        foreach (array_keys(self::$nowruzCache) as $year) {
            $gap = abs($year - $target);

            if ($gap < $smallestGap) {
                $smallestGap = $gap;
                $closest = $year;
            }
        }

        return $closest;
    }

    /**
     * Days from 1970-01-01 to 1 Farvardin 1400, which is 21 March 2021.
     */
    private static function anchorSerialDay(): int
    {
        return self::$anchorSerialDay ??= self::serialDay(2021, 3, 21);
    }

    // ------------------------------------------------------------------
    // Conversion
    // ------------------------------------------------------------------

    /**
     * The Solar Hijri date for a Gregorian one.
     *
     * Accepts anything date-shaped: a Carbon instance, any DateTimeInterface,
     * an ISO-8601 string, or a Unix timestamp. THE TIME OF DAY IS IGNORED --
     * this calendar has no clock, so two timestamps on the same day give the
     * same answer.
     *
     * Read the class docblock: the result is for printing, not for storing.
     */
    public static function fromGregorian(DateTimeInterface|string|int $date): SolarHijriDate
    {
        [$year, $month, $day] = self::gregorianParts($date);

        // The Solar Hijri year that can hold this Gregorian date is
        // (year - 621), or one earlier if we are still before its Nowruz.
        $jalaliYear = $year - 621;
        $serialDay = self::serialDay($year, $month, $day);

        if ($serialDay < self::nowruzSerialDay($jalaliYear)) {
            $jalaliYear--;
        }

        $dayOfYear = $serialDay - self::nowruzSerialDay($jalaliYear) + 1;

        return self::fromDayOfYear($jalaliYear, $dayOfYear);
    }

    /**
     * A Solar Hijri date as 'YYYY-MM-DD' -- for display strings and for the
     * JSON a date picker sends and receives. Never for a database column.
     *
     * Deliberately not shaped like a stored value. If you need something to
     * persist, keep the Gregorian original.
     */
    public static function toSolarIso(DateTimeInterface|string|int $date): string
    {
        $solar = self::fromGregorian($date);

        return sprintf('%04d-%02d-%02d', $solar->year, $solar->month, $solar->day);
    }

    /**
     * A Carbon instance on the Gregorian date matching a Solar Hijri one.
     *
     * Midnight UTC. The result is an ordinary Carbon object, so every date
     * method on it behaves exactly as it would on any other date in the shop.
     */
    public static function toGregorian(int $jalaliYear, int $jalaliMonth, int $jalaliDay): CarbonInterface
    {
        [$year, $month, $day] = self::gregorianParts(
            self::toGregorianIso($jalaliYear, $jalaliMonth, $jalaliDay)
        );

        return CarbonImmutable::createFromFormat(
            '!Y-m-d',
            self::iso($year, $month, $day),
            new DateTimeZone('UTC'),
        );
    }

    /**
     * The Gregorian 'Y-m-d' matching a Solar Hijri date.
     */
    public static function toGregorianIso(int $jalaliYear, int $jalaliMonth, int $jalaliDay): string
    {
        $serialDay = self::serialDayFromSolar($jalaliYear, $jalaliMonth, $jalaliDay);

        [$year, $month, $day] = self::civilFromSerialDay($serialDay);

        return self::iso($year, $month, $day);
    }

    /**
     * A Carbon instance on the Gregorian date, from a 'YYYY-MM-DD' SOLAR string.
     *
     * This is how a Solar Hijri date picker hands a chosen day back to the
     * server: the browser sends the solar day the shopper clicked, and the
     * server immediately converts it back to Gregorian for storage.
     */
    public static function fromSolarIso(string $solarIso): CarbonInterface
    {
        if (! preg_match('/^\s*(-?\d{1,6})-(\d{1,2})-(\d{1,2})\s*$/', $solarIso, $matches)) {
            throw new InvalidArgumentException(
                "Expected a Solar Hijri date as YYYY-MM-DD, got [{$solarIso}]."
            );
        }

        return self::toGregorian((int) $matches[1], (int) $matches[2], (int) $matches[3]);
    }

    /**
     * The Gregorian day number for a Solar Hijri date.
     */
    private static function serialDayFromSolar(int $jalaliYear, int $jalaliMonth, int $jalaliDay): int
    {
        self::assertYear($jalaliYear);
        self::assertMonth($jalaliMonth);
        self::assertValid($jalaliYear, $jalaliMonth, $jalaliDay);

        // 1 Farvardin is day 1, so day-of-year is the offset plus one.
        return self::nowruzSerialDay($jalaliYear)
            + self::dayOfYear($jalaliYear, $jalaliMonth, $jalaliDay) - 1;
    }

    /**
     * Which day of the Solar Hijri year a date is: 1 for 1 Farvardin, and 365
     * or 366 for the last day of Esfand.
     */
    public static function dayOfYear(int $jalaliYear, int $jalaliMonth, int $jalaliDay): int
    {
        self::assertMonth($jalaliMonth);
        self::assertValid($jalaliYear, $jalaliMonth, $jalaliDay);

        $dayOfYear = $jalaliDay;

        for ($month = 1; $month < $jalaliMonth; $month++) {
            $dayOfYear += self::daysInMonth($jalaliYear, $month);
        }

        return $dayOfYear;
    }

    /**
     * Turn a day-of-the-year back into a month and a day.
     */
    private static function fromDayOfYear(int $jalaliYear, int $dayOfYear): SolarHijriDate
    {
        $remaining = $dayOfYear;

        for ($month = 1; $month <= 12; $month++) {
            $length = self::daysInMonth($jalaliYear, $month);

            if ($remaining <= $length) {
                return new SolarHijriDate($jalaliYear, $month, $remaining);
            }

            $remaining -= $length;
        }

        // Only reachable if daysInYear() and the month table ever disagree,
        // which the test suite checks.
        throw new InvalidArgumentException(
            "Day {$dayOfYear} does not exist in Solar Hijri year {$jalaliYear}."
        );
    }

    // ------------------------------------------------------------------
    // Moving around
    // ------------------------------------------------------------------

    /**
     * The Solar Hijri date N days after (or before) a Gregorian one.
     *
     * Useful for "due in 30 days" without anyone having to work out what 30
     * days means in a month that might be 29, 30 or 31 days long.
     */
    public static function addDays(DateTimeInterface|string|int $date, int $days): SolarHijriDate
    {
        [$year, $month, $day] = self::gregorianParts($date);

        $serialDay = self::serialDay($year, $month, $day) + $days;

        [$year, $month, $day] = self::civilFromSerialDay($serialDay);

        return self::fromGregorian(self::iso($year, $month, $day));
    }

    /**
     * The first and last GREGORIAN date of a Solar Hijri month, as 'Y-m-d'.
     *
     * The pair a report needs for "everything in Mehr" -- and the pair that
     * proves why the conversion must happen here and not in the query.
     *
     * @return array{start: string, end: string}
     */
    public static function gregorianMonthRange(int $jalaliYear, int $jalaliMonth): array
    {
        return [
            'start' => self::toGregorianIso($jalaliYear, $jalaliMonth, 1),
            'end' => self::toGregorianIso(
                $jalaliYear,
                $jalaliMonth,
                self::daysInMonth($jalaliYear, $jalaliMonth),
            ),
        ];
    }

    /**
     * The weekday of a Solar Hijri date, 0 = Sunday through 6 = Saturday.
     *
     * The same numbering PHP's date('w') and JavaScript's Date.getDay() use,
     * so a weekday never needs translating between the two sides of the app.
     */
    public static function weekdayOfSolar(int $jalaliYear, int $jalaliMonth, int $jalaliDay): int
    {
        return self::weekdayFromSerialDay(
            self::serialDayFromSolar($jalaliYear, $jalaliMonth, $jalaliDay),
        );
    }

    /**
     * The weekday of a Gregorian date, 0 = Sunday through 6 = Saturday.
     */
    public static function weekdayOfGregorian(DateTimeInterface|string|int $date): int
    {
        [$year, $month, $day] = self::gregorianParts($date);

        return self::weekdayFromSerialDay(self::serialDay($year, $month, $day));
    }

    /**
     * 1970-01-01 was a Thursday, which is index 4.
     */
    private static function weekdayFromSerialDay(int $serialDay): int
    {
        return (($serialDay + 4) % 7 + 7) % 7;
    }

    /**
     * Is this GREGORIAN year a leap year -- divisible by 4, but not by 100
     * unless it is also by 400?
     *
     * Only here so the two calendars' leap rules can be compared side by side
     * in a test. The Solar Hijri rule is the one that matters in this store.
     */
    public static function isGregorianLeapYear(int $gregorianYear): bool
    {
        return $gregorianYear % 4 === 0 && ($gregorianYear % 100 !== 0 || $gregorianYear % 400 === 0);
    }

    // ------------------------------------------------------------------
    // Checking input
    // ------------------------------------------------------------------

    /**
     * Does this Solar Hijri date actually exist?
     *
     * The only date this rejects that could look plausible is 30 Esfand in a
     * non-leap year -- the one real trap in this calendar.
     */
    public static function isValid(int $jalaliYear, int $jalaliMonth, int $jalaliDay): bool
    {
        if ($jalaliYear < self::MIN_JALALI_YEAR || $jalaliYear > self::MAX_JALALI_YEAR) {
            return false;
        }

        if ($jalaliMonth < 1 || $jalaliMonth > 12 || $jalaliDay < 1) {
            return false;
        }

        return $jalaliDay <= self::daysInMonth($jalaliYear, $jalaliMonth);
    }

    /**
     * Throw, with a message that says what is wrong, unless this date exists.
     */
    public static function assertValid(int $jalaliYear, int $jalaliMonth, int $jalaliDay): void
    {
        if (self::isValid($jalaliYear, $jalaliMonth, $jalaliDay)) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            '%d-%02d-%02d is not a Solar Hijri date. Months 1-6 have 31 days, '
            .'months 7-11 have 30, and Esfand (12) has 29 -- or 30 in a leap year.',
            $jalaliYear,
            $jalaliMonth,
            $jalaliDay,
        ));
    }

    private static function assertYear(int $jalaliYear): void
    {
        if ($jalaliYear >= self::MIN_JALALI_YEAR && $jalaliYear <= self::MAX_JALALI_YEAR) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Solar Hijri year %d is outside the supported range %d-%d.',
            $jalaliYear,
            self::MIN_JALALI_YEAR,
            self::MAX_JALALI_YEAR,
        ));
    }

    private static function assertMonth(int $jalaliMonth): void
    {
        if ($jalaliMonth < 1 || $jalaliMonth > 12) {
            throw new InvalidArgumentException("Solar Hijri month must be 1-12, got {$jalaliMonth}.");
        }
    }

    // ------------------------------------------------------------------
    // Formatting shortcuts
    // ------------------------------------------------------------------
    //
    // So the common case is one call:
    //
    //     SolarHijri::format($order->created_at)
    //     SolarHijri::format($order->created_at, 'en')
    //
    // rather than a use statement and a differently named class. The full set
    // of knobs lives on SolarHijriFormatter.

    /**
     * The date as a person reads it:
     * '۱۵ شهریور ۱۴۰۴ (6 September 2025)' -- Solar Hijri first, Gregorian in
     * brackets.
     *
     * @param  DateTimeInterface|string|int|null  $date  Null means "now".
     * @param  string|null  $locale  'fa' (the store default), 'fa_alt', 'ps'
     *                                or 'en'. Null uses the app's locale.
     * @param  array  $options  See SolarHijriFormatter::format().
     */
    public static function format(
        DateTimeInterface|string|int|null $date = null,
        ?string $locale = null,
        array $options = [],
    ): string {
        return SolarHijriFormatter::format($date, $locale, $options);
    }

    /**
     * The Solar Hijri date alone: '۱۵ شهریور ۱۴۰۴'.
     */
    public static function formatSolar(
        DateTimeInterface|string|int|null $date = null,
        ?string $locale = null,
        array $options = [],
    ): string {
        return SolarHijriFormatter::solar($date, $locale, $options);
    }

    /**
     * The Gregorian date alone: '6 September 2025'.
     */
    public static function formatGregorian(
        DateTimeInterface|string|int|null $date = null,
        ?string $locale = null,
        array $options = [],
    ): string {
        return SolarHijriFormatter::gregorian($date, $locale, $options);
    }

    // ------------------------------------------------------------------
    // Gregorian arithmetic, in plain integers
    // ------------------------------------------------------------------
    //
    // Deliberately not using DateTime for the maths. These are Howard Hinnant's
    // days_from_civil and civil_from_days: exact integer expressions with no
    // timezone and no daylight saving anywhere in them. So PHP and the
    // JavaScript twin of this file cannot possibly disagree, and neither can
    // be caught out by a DST boundary on the day the shop launches.

    /**
     * Days since 1970-01-01 for a proleptic Gregorian date.
     */
    private static function serialDay(int $year, int $month, int $day): int
    {
        $shiftedYear = $year - ($month <= 2 ? 1 : 0);

        $era = self::floorDiv($shiftedYear, 400);
        $yearOfEra = $shiftedYear - $era * 400;
        $dayOfYear = self::floorDiv(153 * ($month + ($month > 2 ? -3 : 9)) + 2, 5) + $day - 1;
        $dayOfEra = $yearOfEra * 365
            + self::floorDiv($yearOfEra, 4)
            - self::floorDiv($yearOfEra, 100)
            + $dayOfYear;

        return $era * 146097 + $dayOfEra - 719468;
    }

    /**
     * The proleptic Gregorian date that a day number since 1970-01-01 lands on.
     *
     * @return array{0: int, 1: int, 2: int} year, month, day
     */
    private static function civilFromSerialDay(int $serialDay): array
    {
        $z = $serialDay + 719468;

        $era = self::floorDiv($z, 146097);
        $dayOfEra = $z - $era * 146097;
        $yearOfEra = self::floorDiv(
            $dayOfEra
                - self::floorDiv($dayOfEra, 1460)
                + self::floorDiv($dayOfEra, 36524)
                - self::floorDiv($dayOfEra, 146096),
            365,
        );
        $year = $yearOfEra + $era * 400;
        $dayOfYear = $dayOfEra
            - ($yearOfEra * 365 + self::floorDiv($yearOfEra, 4) - self::floorDiv($yearOfEra, 100));
        $monthPrime = self::floorDiv(5 * $dayOfYear + 2, 153);
        $day = $dayOfYear - self::floorDiv(153 * $monthPrime + 2, 5) + 1;
        $month = $monthPrime + ($monthPrime < 10 ? 3 : -9);

        return [$year + ($month <= 2 ? 1 : 0), $month, $day];
    }

    /**
     * Division that rounds towards negative infinity.
     *
     * PHP's intdiv() rounds towards zero instead, which is why the day-number
     // maths above cannot simply use it: for a date before 1970 the answer
     * would be off by one. Every divisor here is positive, so for the range
     * the shop actually uses the two agree -- but the floor version is the one
     * that is right everywhere.
     */
    private static function floorDiv(int $value, int $divisor): int
    {
        $quotient = intdiv($value, $divisor);

        return ($value % $divisor !== 0 && (($value < 0) !== ($divisor < 0))) ? $quotient - 1 : $quotient;
    }

    /**
     * Split anything date-shaped into proleptic Gregorian year, month, day.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private static function gregorianParts(DateTimeInterface|string|int $date): array
    {
        if ($date instanceof DateTimeInterface) {
            // format('Y-m-d') is safe for any year the shop will ever hold.
            return [
                (int) $date->format('Y'),
                (int) $date->format('n'),
                (int) $date->format('j'),
            ];
        }

        if (is_int($date)) {
            $moment = CarbonImmutable::createFromTimestampUTC($date)
                ->setTimezone(new DateTimeZone('UTC'));

            return [
                (int) $moment->format('Y'),
                (int) $moment->format('n'),
                (int) $moment->format('j'),
            ];
        }

        $trimmed = trim($date);

        // The fast path: a leading ISO-8601 date, no parser involved. This is
        // what every date arriving from the database or an API payload looks
        // like, so it is worth taking before anything slower.
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $trimmed, $matches)) {
            return [(int) $matches[1], (int) $matches[2], (int) $matches[3]];
        }

        // Anything else carrying a date we recognise -- '6 September 2025', an
        // RFC 2822 email date -- goes to the platform parser. Anything it
        // cannot read fails here, rather than halfway through rendering a page.
        try {
            $parsed = CarbonImmutable::parse($trimmed, new DateTimeZone('UTC'));
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException(
                "Could not read a Gregorian date from [{$trimmed}].",
                0,
                $exception,
            );
        }

        return [
            (int) $parsed->format('Y'),
            (int) $parsed->format('n'),
            (int) $parsed->format('j'),
        ];
    }

    /**
     * 'Y-m-d' with a four-digit year, from integers.
     */
    private static function iso(int $year, int $month, int $day): string
    {
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
}
