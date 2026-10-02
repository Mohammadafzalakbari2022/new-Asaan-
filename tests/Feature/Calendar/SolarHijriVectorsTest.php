<?php

use Cartxis\Calendar\Support\SolarHijri;
use Cartxis\Calendar\Support\SolarHijriVectors;

/*
|--------------------------------------------------------------------------
| The Solar Hijri engine against shared, independently produced vectors
|--------------------------------------------------------------------------
|
| Every expected figure below is read from solar-hijri-vectors.json, which was
| produced by ICU's Persian calendar through Intl.DateTimeFormat: an
| implementation sharing no code with this one. The two engines agreeing is
| evidence; a test written against this engine's own output would only prove it
| is self-consistent.
|
| The same file is read by resources/js/lib/solar-hijri.test.ts so the server
| and the browser cannot quietly disagree about what today's date is.
|
| Datasets are positional on purpose. Pest hands named arguments from the row
| keys, so a closure that does not name every key in the vector fails on
| "unknown named parameter" before it ever reaches the engine.
|
*/

/** The vectors as plain positional rows: solar, gregorian, leap, monthLength, weekday. */
function solarVectorRows(): array
{
    return array_map(
        fn (array $row): array => [
            $row['solar'],
            $row['gregorian'],
            $row['leapYear'],
            $row['daysInMonth'],
            $row['weekday'],
        ],
        SolarHijriVectors::section('solarToGregorian')
    );
}

/** The vectors as plain positional rows: gregorian, solar, leap, weekday. */
function gregorianVectorRows(): array
{
    return array_map(
        fn (array $row): array => [$row['gregorian'], $row['solar'], $row['leapYear'], $row['weekday']],
        SolarHijriVectors::section('gregorianToSolar')
    );
}

it('counts from the same anchor the vectors were built on', function () {
    $anchor = SolarHijriVectors::anchor();

    expect(SolarHijri::toGregorianIso(1400, 1, 1))->toBe($anchor['gregorian'])
        ->and(SolarHijri::toSolarIso($anchor['gregorian']))->toBe($anchor['solar']);
});

it('uses the leap remainders the vectors were derived from', function () {
    expect(SolarHijri::leapRemainders())->toBe(SolarHijriVectors::leapRemainders());
});

it('turns every solar date in the vectors into the right Gregorian day', function (string $solar, string $gregorian) {
    expect(SolarHijri::toGregorianIso(...array_map('intval', explode('-', $solar))))->toBe($gregorian);
})->with(solarVectorRows());

it('agrees with the vectors about which solar years are leap years, and how long their months are', function (string $solar, string $gregorian, bool $leapYear, int $daysInMonth) {
    [$year, $month] = array_map('intval', explode('-', $solar));

    expect(SolarHijri::isLeapYear($year))->toBe($leapYear)
        ->and(SolarHijri::daysInMonth($year, $month))->toBe($daysInMonth)
        ->and(SolarHijri::daysInYear($year))->toBe($leapYear ? 366 : 365);
})->with(solarVectorRows());

it('gives the right day of the week for every solar date in the vectors', function (string $solar, string $gregorian, bool $leapYear, int $daysInMonth, int $weekday) {
    [$year, $month, $day] = array_map('intval', explode('-', $solar));

    expect(SolarHijri::weekdayOfSolar($year, $month, $day))->toBe($weekday)
        ->and(SolarHijri::weekdayOfGregorian($gregorian))->toBe($weekday);
})->with(solarVectorRows());

it('turns every Gregorian date in the vectors into the right solar day', function (string $gregorian, string $solar) {
    expect(SolarHijri::toSolarIso($gregorian))->toBe($solar);
})->with(gregorianVectorRows());

it('lands on the right weekday for every Gregorian date in the vectors', function (string $gregorian, string $solar, bool $leapYear, int $weekday) {
    [$year, $month, $day] = array_map('intval', explode('-', $solar));

    expect(SolarHijri::weekdayOfGregorian($gregorian))->toBe($weekday)
        ->and(SolarHijri::weekdayOfSolar($year, $month, $day))->toBe($weekday)
        ->and(SolarHijri::isLeapYear($year))->toBe($leapYear);
})->with(gregorianVectorRows());

it('puts Nowruz on the right Gregorian day in every year the vectors cover', function (int $year, string $gregorian, bool $leapYear) {
    expect(SolarHijri::nowruzGregorian($year))->toBe($gregorian)
        ->and(SolarHijri::isLeapYear($year))->toBe($leapYear);
})->with(SolarHijriVectors::section('nowruzByYear'));

it('round trips every solar date in the vectors through Gregorian and back', function (string $solar, string $gregorian) {
    expect(SolarHijri::toSolarIso(SolarHijri::toGregorianIso(...array_map('intval', explode('-', $solar)))))->toBe($solar);
})->with(solarVectorRows());

it('describes every solar date in the vectors consistently as a date object', function (string $solar, string $gregorian, bool $leapYear, int $daysInMonth, int $weekday) {
    $date = SolarHijri::fromGregorian($gregorian);

    expect($date->toSolarIso())->toBe($solar)
        ->and($date->weekday())->toBe($weekday)
        ->and($date->yearIsLeap())->toBe($leapYear)
        ->and($date->daysInMonth())->toBe($daysInMonth)
        ->and($date->toGregorianIso())->toBe($gregorian);
})->with(solarVectorRows());