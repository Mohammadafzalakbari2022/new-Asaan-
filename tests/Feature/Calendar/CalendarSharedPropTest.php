<?php

namespace Tests\Feature\Calendar;

use Cartxis\Calendar\Support\CalendarConfig;

/**
 * The server owns the store's calendar decisions and hands them to the browser:
 *
 *   CalendarConfig::shared()  ->  Inertia prop `calendar`  ->  useCalendar()
 *
 * These tests hold the two ends of that contract together -- the exact shape
 * the frontend reads, and the config key each field comes from -- so renaming
 * one end without the other fails here rather than silently in a table cell.
 */
it('shares the full set of calendar settings the frontend reads', function () {
    expect(array_keys(CalendarConfig::shared()))->toBe([
        'primary',
        'secondary',
        'numerals',
        'secondaryNumerals',
        'bracket',
        'weekStartsOn',
        'minYear',
        'maxYear',
    ]);
});

it('mirrors the calendar config rather than keeping its own copy', function () {
    config([
        'calendar.primary' => 'gregorian',
        'calendar.secondary' => 'none',
        'calendar.numerals' => 'latn',
        'calendar.secondary_numerals' => 'arab',
        'calendar.bracket' => '· {secondary}',
        'calendar.week_starts_on' => 0,
        'calendar.validated_min_year' => 1400,
        'calendar.validated_max_year' => 1499,
    ]);

    expect(CalendarConfig::shared())->toBe([
        'primary' => 'gregorian',
        'secondary' => 'none',
        'numerals' => 'latn',
        'secondaryNumerals' => 'arab',
        'bracket' => '· {secondary}',
        'weekStartsOn' => 0,
        'minYear' => 1400,
        'maxYear' => 1499,
    ]);
});

it('casts the week and year numbers to integers', function () {
    $shared = CalendarConfig::shared();

    expect($shared['weekStartsOn'])->toBeInt();
    expect($shared['minYear'])->toBeInt();
    expect($shared['maxYear'])->toBeInt();
});

it('falls back to the store defaults when a value is missing', function () {
    config(['calendar.numerals' => null]);

    expect(CalendarConfig::shared()['numerals'])->toBe('fa');
});
