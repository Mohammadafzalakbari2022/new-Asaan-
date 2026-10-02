<?php

namespace Cartxis\Calendar\Support;

use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * One date in the Solar Hijri calendar: year, month and day.
 *
 * A plain, immutable carrier for three integers. It has no idea about
 * formatting, locale or numerals -- that is SolarHijriFormatter's job -- so it
 * can be passed around, compared and serialised without dragging any of that
 * with it.
 *
 * Like the rest of this package it is a DISPLAY value. It is not something to
 * save; see the SolarHijri class docblock.
 */
final class SolarHijriDate implements JsonSerializable, Stringable
{
    /**
     * @param  int  $year  Solar Hijri year, e.g. 1404.
     * @param  int  $month  1 (Farvardin) to 12 (Esfand).
     * @param  int  $day  1 to 29, 30 or 31 depending on the month and year.
     *
     * @throws \InvalidArgumentException if the date does not exist, which is
     *                                  the only realistic way to get this wrong:
     *                                  30 Esfand in a non-leap year.
     */
    public function __construct(
        public readonly int $year,
        public readonly int $month,
        public readonly int $day,
    ) {
        SolarHijri::assertValid($year, $month, $day);
    }

    /**
     * A date from a 'YYYY-MM-DD' Solar Hijri string.
     *
     * @throws \InvalidArgumentException if the string is malformed or the date
     *                                  does not exist.
     */
    public static function fromIso(string $solarIso): self
    {
        if (! preg_match('/^\s*(-?\d{1,6})-(\d{1,2})-(\d{1,2})\s*$/', $solarIso, $matches)) {
            throw new \InvalidArgumentException(
                "Expected a Solar Hijri date as YYYY-MM-DD, got [{$solarIso}]."
            );
        }

        return new self((int) $matches[1], (int) $matches[2], (int) $matches[3]);
    }

    /**
     * The Solar Hijri date for any Gregorian input.
     */
    public static function fromGregorian(DateTimeInterface|string|int $date): self
    {
        return SolarHijri::fromGregorian($date);
    }

    /**
     * Today, in the Solar Hijri calendar, in the app's configured timezone.
     */
    public static function today(?string $timezone = null): self
    {
        return SolarHijri::fromGregorian(
            Carbon::now($timezone ?: CalendarConfig::get('app.timezone')),
        );
    }

    /**
     * The Gregorian date this one falls on, as a Carbon instance at midnight UTC.
     */
    public function toGregorian(): CarbonInterface
    {
        return SolarHijri::toGregorian($this->year, $this->month, $this->day);
    }

    /**
     * The Gregorian date this one falls on, as 'Y-m-d'.
     */
    public function toGregorianIso(): string
    {
        return SolarHijri::toGregorianIso($this->year, $this->month, $this->day);
    }

    /**
     * 'YYYY-MM-DD' in the SOLAR calendar. For a date picker's value, not a column.
     */
    public function toSolarIso(): string
    {
        return sprintf('%04d-%02d-%02d', $this->year, $this->month, $this->day);
    }

    /**
     * Is this Solar Hijri year a leap year of 366 days?
     */
    public function yearIsLeap(): bool
    {
        return SolarHijri::isLeapYear($this->year);
    }

    /**
     * How many days are in this Solar Hijri month.
     */
    public function daysInMonth(): int
    {
        return SolarHijri::daysInMonth($this->year, $this->month);
    }

    /**
     * Which day of the Solar Hijri year this is: 1 for 1 Farvardin.
     */
    public function dayOfYear(): int
    {
        return SolarHijri::dayOfYear($this->year, $this->month, $this->day);
    }

    /**
     * The weekday, 0 = Sunday through 6 = Saturday.
     */
    public function weekday(): int
    {
        return SolarHijri::weekdayOfSolar($this->year, $this->month, $this->day);
    }

    /**
     * The next day in the Solar Hijri calendar.
     */
    public function nextDay(): self
    {
        return self::fromGregorian(
            $this->toGregorian()->addDay(),
        );
    }

    /**
     * The previous day in the Solar Hijri calendar.
     */
    public function previousDay(): self
    {
        return self::fromGregorian(
            $this->toGregorian()->subDay(),
        );
    }

    /**
     * Compare against another Solar Hijri date: -1, 0 or 1.
     */
    public function compareTo(self $other): int
    {
        return [$this->year, $this->month, $this->day]
            <=> [$other->year, $other->month, $other->day];
    }

    public function isBefore(self $other): bool
    {
        return $this->compareTo($other) < 0;
    }

    public function isAfter(self $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    public function equals(self $other): bool
    {
        return $this->compareTo($other) === 0;
    }

    /**
     * JSON, in the shape a date picker wants. The Gregorian date is included
     * on purpose: the browser must send back a Gregorian value for the server
     * to store, so handing it both saves every caller a second lookup.
     *
     * @return array{year: int, month: int, day: int, iso: string, gregorian: string, weekday: int}
     */
    public function jsonSerialize(): array
    {
        return [
            'year' => $this->year,
            'month' => $this->month,
            'day' => $this->day,
            'iso' => $this->toSolarIso(),
            'gregorian' => $this->toGregorianIso(),
            'weekday' => $this->weekday(),
        ];
    }

    /**
     * 'YYYY-MM-DD' in the solar calendar, so a date printed bare in a Blade
     * template or a log line is never mistaken for a stored Gregorian value.
     */
    public function __toString(): string
    {
        return $this->toSolarIso();
    }
}
