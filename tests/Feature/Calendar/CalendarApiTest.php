<?php

use Cartxis\Calendar\Support\SolarHijri;
use Cartxis\Calendar\Support\SolarHijriFormatter;
use Cartxis\Calendar\Support\SolarHijriLocale;

/*
|--------------------------------------------------------------------------
| The calendar API, which is the seam a date picker draws itself from
|--------------------------------------------------------------------------
|
| A native date input cannot show Solar Hijri, so the picker has to be told how
| to lay out a month: which weekday the 1st falls on, how long the month is, and
| which Gregorian day sits behind each Solar Hijri day.
|
| The last of those is the important one. The shopper picks a Solar Hijri day,
| but what reaches the database has to be Gregorian, and the only thing standing
| between a wrong figure here and a wrong order date is the 'gregorian' field on
| every cell. So it is checked against the vectors, not just checked for existing.
|
*/

it('serves a month grid a picker can draw without doing any calendar maths', function () {
    $response = $this->getJson('/api/v1/calendar/months/1404/6');

    $response->assertOk()
        ->assertJsonPath('year', 1404)
        ->assertJsonPath('month', 6)
        ->assertJsonStructure([
            'year', 'month', 'isLeapYear', 'daysInMonth', 'monthName', 'weekdays', 'weeks',
        ]);

    $payload = $response->json();

    // Every row is a full week, so the grid has no ragged edge to draw.
    foreach ($payload['weeks'] as $week) {
        expect($week)->toHaveCount(7);
    }

    // The days of the month are all present exactly once, in order.
    $inMonth = array_values(array_filter(
        array_merge(...$payload['weeks']),
        fn (array $day): bool => $day['inMonth'],
    ));

    expect($inMonth)->toHaveCount($payload['daysInMonth'])
        ->and(array_column($inMonth, 'day'))->toBe(range(1, $payload['daysInMonth']));
});

it('carries the Gregorian day behind every Solar Hijri day', function () {
    $weeks = $this->getJson('/api/v1/calendar/months/1404/6')->json('weeks');

    foreach (array_merge(...$weeks) as $day) {
        // The picker sends 'gregorian' back untouched, so it has to survive the
        // round trip through the engine unchanged.
        expect($day['gregorian'])->toBe(SolarHijri::toGregorianIso($day['year'], $day['month'], $day['day']))
            ->and(SolarHijri::toSolarIso($day['gregorian']))->toBe($day['iso']);
    }
});

it('lays the week out from Saturday, where the Afghan week starts', function () {
    $payload = $this->getJson('/api/v1/calendar/months/1404/6')->json();

    expect($payload['weekdays'])->toHaveCount(7);

    // Every row is a full week and no row wraps, so the first cell of every row
    // is the same weekday: Saturday.
    foreach ($payload['weeks'] as $week) {
        expect($week[0]['weekday'])->toBe(6);
    }

    // Sunbula 1404 begins on a Saturday, so it needs no leading padding at
    // all and the 1st is already in the first cell. Checked here because it is
    // the case that would be broken by an off-by-one in the padding maths.
    expect($payload['weeks'][0][0]['inMonth'])->toBeTrue()
        ->and($payload['weeks'][0][0]['day'])->toBe(1);
});

it('pads a month that starts mid-week so the 1st lands under its own weekday', function () {
    // Hamal 1404 starts on a Friday, so six leading days come from
    // Hoot and must be greyed out rather than dropped.
    $payload = $this->getJson('/api/v1/calendar/months/1404/1')->json();

    expect(SolarHijri::weekdayOfSolar(1404, 1, 1))->toBe(5)
        ->and($payload['weeks'][0][0]['inMonth'])->toBeFalse()
        ->and($payload['weeks'][0][1]['inMonth'])->toBeFalse();

    $first = collect($payload['weeks'][0])->firstWhere('inMonth', true);

    expect($first['day'])->toBe(1)
        ->and($first['weekday'])->toBe(5);
});

it('names the month in the store default language, which is Dari', function () {
    $payload = $this->getJson('/api/v1/calendar/months/1404/6')->json();

    expect(config('calendar.locale'))->toBe('fa')
        ->and(SolarHijriLocale::storeDefault())->toBe('fa')
        ->and($payload['monthName'])->toBe(SolarHijriLocale::monthName(6, 'fa'))
        ->and($payload['monthName'])->toBe('سنبله');
});

it('lets the store change its default date language in one place', function () {
    // CALENDAR_LOCALE used to be documented in the config file as the store's
    // default and read by nothing at all, so setting it changed nothing. It is
    // the fallback for callers with no request to ask -- a PDF, a queued job, a
    // Blade template handed no locale.
    config(['calendar.locale' => 'en']);

    expect(SolarHijriLocale::storeDefault())->toBe('en')
        ->and(solar_hijri('2025-09-06'))->toContain('September')
        ->and(solar_hijri('2025-09-06'))->toContain('Sunbula');

    config(['calendar.locale' => 'fa']);

    expect(SolarHijriLocale::storeDefault())->toBe('fa')
        ->and(solar_hijri('2025-09-06'))->toContain('سنبله');
});

it('still lets a reader who has chosen English keep English, whatever the store default is', function () {
    config(['calendar.locale' => 'fa']);

    // The storefront switcher calls app()->setLocale(); the calendar follows it
    // rather than the store default, which is the documented promise.
    app()->setLocale('en');

    expect($this->getJson('/api/v1/calendar/months/1404/6')->json('monthName'))->toBe('Sunbula');

    app()->setLocale('fa');

    expect($this->getJson('/api/v1/calendar/months/1404/6')->json('monthName'))->toBe('سنبله');
});

it('gives a reader who asked for English English month names', function () {
    $payload = $this->getJson('/api/v1/calendar/months/1404/6?locale=en')->json();

    expect($payload['monthName'])->toBe('Sunbula');
});

it('greets the year at Nowruz, with the year lengths either side of it', function () {
    // 1404 is an ordinary year: Nowruz falls on 21 March and the year is 365
    // days long. Checked against the engine rather than hardcoded, so a change
    // to the leap rule shows up here instead of in a customer's calendar.
    $this->getJson('/api/v1/calendar/nowruz/1404')
        ->assertOk()
        ->assertJsonPath('year', 1404)
        ->assertJsonPath('isLeapYear', SolarHijri::isLeapYear(1404))
        ->assertJsonPath('isLeapYear', false)
        ->assertJsonPath('nowruz', '2025-03-21')
        ->assertJsonPath('nowruzMarchDay', 21)
        ->assertJsonPath('daysInYear', 365)
        ->assertJsonPath('previous', SolarHijri::nowruzGregorian(1403))
        ->assertJsonPath('next', SolarHijri::nowruzGregorian(1405))
        ->assertJsonCount(12, 'monthLengths');
});

it('refuses a year outside the range the store will print, and says what it accepts', function () {
    $this->getJson('/api/v1/calendar/nowruz/1300')->assertStatus(422);
    $this->getJson('/api/v1/calendar/nowruz/1600')->assertStatus(422);
});

it('refuses a month that does not exist', function () {
    $this->getJson('/api/v1/calendar/months/1404/0')->assertStatus(422);
    $this->getJson('/api/v1/calendar/months/1404/13')->assertStatus(422);
});

it('answers what today is, in both calendars at once', function () {
    $response = $this->getJson('/api/v1/calendar/today')->assertOk();

    $payload = $response->json();
    $solar = SolarHijri::fromGregorian($payload['gregorian']);

    expect($payload['gregorian'])->toBe(now()->format('Y-m-d'))
        ->and($payload['month']['year'])->toBe($solar->year)
        ->and($payload['month']['month'])->toBe($solar->month);

    // The secondary half stays in Latin digits on purpose: a bracketed reference
    // is only useful if it can be copied into a bank form without retyping.
    expect($payload['text'])->toBe(SolarHijriFormatter::format($payload['gregorian']))
        ->and($payload['text'])->toContain(now()->format('j'))
        ->and($payload['text'])->toContain(now()->format('F'));
});

it('serves the month and weekday names the header row needs', function () {
    $this->getJson('/api/v1/calendar/names?locale=fa')
        ->assertOk()
        ->assertJsonStructure(['months', 'weekdays'])
        ->assertJsonCount(12, 'months')
        ->assertJsonCount(7, 'weekdays');
});