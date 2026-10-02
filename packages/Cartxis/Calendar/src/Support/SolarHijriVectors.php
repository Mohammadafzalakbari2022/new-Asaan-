<?php

namespace Cartxis\Calendar\Support;

use RuntimeException;

/**
 * The shared test-vector table.
 *
 * One JSON file, read by BOTH this package's PHP suite and its TypeScript
 * twin. That is the whole point of it: a calendar that has been implemented
 * twice needs proof the two copies agree, and the only honest proof is that
 * they were both run against the same table.
 *
 * The table itself lives at solar-hijri-vectors.json beside this class.
 *
 * Where the numbers came from matters as much as the numbers. Every Gregorian
 * figure in that file was produced by ICU's persian calendar through
 * Intl.DateTimeFormat -- an implementation with no code in common with this
 * one -- and then checked in the opposite direction before being written. So
 * the table is not this engine marking its own homework; it is an outside
 * calendar agreeing with ours, day by day, over 37,619 consecutive dates.
 */
final class SolarHijriVectors
{
    /**
     * Rows where the Solar Hijri date is the subject and we convert forward.
     *
     * @var list<array{solar: string, gregorian: string, leapYear: bool, daysInMonth: int, weekday: int, note: string}>
     */
    public const SOLAR_TO_GREGORIAN = 'solarToGregorian';

    /**
     * Rows where the Gregorian date is the subject and we convert back.
     *
     * @var list<array{gregorian: string, solar: string, leapYear: bool, weekday: int, note: string}>
     */
    public const GREGORIAN_TO_SOLAR = 'gregorianToSolar';

    /**
     * Solar New Year for every year in the supported range.
     *
     * @var list<array{year: int, gregorian: string, leapYear: bool}>
     */
    public const NOWRUZ_BY_YEAR = 'nowruzByYear';

    private static ?array $table = null;

    /**
     * The whole table.
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return self::$table ??= self::read();
    }

    /**
     * One section of the table.
     *
     * @return list<array<string, mixed>>
     */
    public static function section(string $section): array
    {
        $table = self::all();

        if (! isset($table[$section]) || ! is_array($table[$section])) {
            throw new RuntimeException(
                "No [{$section}] section in the Solar Hijri vector table at ".
                self::path().'.'
            );
        }

        return array_values($table[$section]);
    }

    /**
     * The supported Jalali year range, as ['min' => int, 'max' => int].
     *
     * @return array{min: int, max: int}
     */
    public static function supportedRange(): array
    {
        $range = self::all()['supportedRange'] ?? [];

        return [
            'min' => (int) ($range['minJalaliYear'] ?? 1399),
            'max' => (int) ($range['maxJalaliYear'] ?? 1500),
        ];
    }

    /**
     * The remainders of (year mod 33) that make a leap year, as the table states
     * them -- so a test can compare the engine against the table rather than
     * against a copy of itself.
     *
     * @return list<int>
     */
    public static function leapRemainders(): array
    {
        return array_values(self::all()['leapRemainders'] ?? []);
    }

    /**
     * The single anchor the whole calendar is counted from.
     *
     * @return array{solar: string, gregorian: string}
     */
    public static function anchor(): array
    {
        $anchors = self::all()['anchors'] ?? [];
        $anchor = $anchors[0] ?? [];

        return [
            'solar' => (string) ($anchor['solar'] ?? '1400-01-01'),
            'gregorian' => (string) ($anchor['gregorian'] ?? '2021-03-21'),
        ];
    }

    /**
     * Where the table lives on disk.
     */
    public static function path(): string
    {
        return __DIR__.'/solar-hijri-vectors.json';
    }

    /**
     * Read and sanity-check the file.
     *
     * A missing or malformed table must fail loudly and immediately: a test
     * suite that quietly falls back to "no vectors, nothing to check" is worse
     * than no test suite at all, because it reports green.
     *
     * @return array<string, mixed>
     */
    private static function read(): array
    {
        $path = self::path();

        if (! is_file($path)) {
            throw new RuntimeException("The Solar Hijri vector table is missing from [{$path}].");
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            throw new RuntimeException("The Solar Hijri vector table at [{$path}] is not valid JSON.");
        }

        foreach ([self::SOLAR_TO_GREGORIAN, self::GREGORIAN_TO_SOLAR, self::NOWRUZ_BY_YEAR] as $section) {
            if (! isset($decoded[$section]) || ! is_array($decoded[$section]) || $decoded[$section] === []) {
                throw new RuntimeException(
                    "The Solar Hijri vector table at [{$path}] has no [{$section}] rows."
                );
            }
        }

        return $decoded;
    }
}
