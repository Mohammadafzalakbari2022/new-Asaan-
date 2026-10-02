<?php

use Cartxis\Calendar\Http\Controllers\MonthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Solar Hijri calendar routes
|--------------------------------------------------------------------------
|
| One endpoint, and it exists only to serve a date picker.
|
| A native <input type="date"> cannot show Solar Hijri -- the browser decides
| its own calendar and offers only Gregorian or Islamic. So a real Solar Hijri
| date picker has to draw its own month grid, and to do that it needs the same
| three things this engine already knows: how long each month is, which weekday
| the 1st falls on, and what the Gregorian date behind each Solar Hijri day is.
|
| It gets all three from here rather than duplicating the calendar in
| JavaScript, which is how two calendars start disagreeing with each other.
|
| Nothing here reads or writes anything. It is public, stateless and side-effect
| free, so it needs no authentication and no CSRF token -- there is no data to
| see and nothing to change.
|
*/

Route::prefix('api/v1/calendar')->name('calendar.')->group(function () {
    Route::get('months/{year}/{month}', [MonthController::class, 'show'])
        ->name('month')
        ->whereNumber('year')
        ->whereNumber('month')
        ->defaults('month', 1);

    Route::get('nowruz/{year}', [MonthController::class, 'nowruz'])
        ->name('nowruz')
        ->whereNumber('year');

    Route::get('names', [MonthController::class, 'names'])
        ->name('names');

    Route::get('today', [MonthController::class, 'today'])
        ->name('today');
});
