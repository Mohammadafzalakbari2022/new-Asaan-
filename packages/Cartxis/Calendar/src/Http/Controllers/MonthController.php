<?php

namespace Cartxis\Calendar\Http\Controllers;

use Cartxis\Calendar\Exceptions\InvalidCalendarMonth;
use Cartxis\Calendar\Exceptions\UnsupportedCalendarYear;
use Cartxis\Calendar\Support\SolarHijri;
use Cartxis\Calendar\Support\SolarHijriCalendar;
use Cartxis\Calendar\Support\SolarHijriFormatter;
use Cartxis\Calendar\Support\SolarHijriLocale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Feeds a Solar Hijri date picker.
 *
 * Every response here is DISPLAY data plus one carry-over value. The
 * 'gregorian' field on each day is the one number a picker has to send back
 * unchanged, because that -- and never the Solar Hijri figure -- is what ends
 * up in the database. See the SolarHijri class docblock.
 *
 * No response here contains a stored value of any kind, so there is nothing to
 * protect and no authorisation to check: the calendar is the same for every
 * shopper in the country.
 */
class MonthController extends Controller
{
    /**
     * One month, as a grid of weeks ready to draw.
     *
     * GET /api/v1/calendar/months/1404/6
     */
    public function show(Request $request, int $year, int $month = 1): JsonResponse
    {
        $this->guard($year, $month);

        return response()->json(SolarHijriCalendar::month($year, $month, $this->locale($request)));
    }

    /**
     * Solar New Year for a year, with the year lengths either side of it.
     *
     * GET /api/v1/calendar/nowruz/1404
     */
    public function nowruz(Request $request, int $year): JsonResponse
    {
        $this->guard($year);

        return response()->json([
            'year' => $year,
            'isLeapYear' => SolarHijri::isLeapYear($year),
            'nowruz' => SolarHijri::nowruzGregorian($year),
            'nowruzMarchDay' => SolarHijri::nowruzMarchDay($year),
            'daysInYear' => SolarHijri::daysInYear($year),
            'monthLengths' => SolarHijri::monthLengths($year),
            'previous' => SolarHijri::nowruzGregorian($year - 1),
            'next' => SolarHijri::nowruzGregorian($year + 1),
        ]);
    }

    /**
     * Month and weekday names in the reader's language.
     *
     * GET /api/v1/calendar/names?locale=fa
     *
     * Fetched once when the picker opens, so the header row does not have to
     * guess -- and so the Afghan Dari and Pashto sets, which Intl gets wrong,
     * arrive from here rather than from the browser's own calendar data.
     */
    public function names(Request $request): JsonResponse
    {
        return response()->json(SolarHijriLocale::table($this->locale($request)));
    }

    /**
     * Today, in both calendars.
     *
     * GET /api/v1/calendar/today
     */
    public function today(Request $request): JsonResponse
    {
        $locale = $this->locale($request);
        $now = now();

        return response()->json([
            'gregorian' => $now->format('Y-m-d'),
            'text' => SolarHijriFormatter::format($now, $locale),
            'weekday' => $now->dayOfWeek,
            'month' => SolarHijriCalendar::monthFor($now, $locale),
        ]);
    }

/**
 * The reader's language.
 *
 * The ?locale= query parameter wins, so a picker can be told which set to
 * use explicitly; otherwise the app's own resolved locale decides, which is
 * what the reader actually chose -- SetLocaleFromCookie and the storefront
 * switcher both call app()->setLocale(). Anything that has chosen nothing
 * gets the store's own default.
 *
 * Note app()->getLocale(), not $request->getLocale(). The latter reads the
 * request's own _locale attribute and falls back to config('app.fallback_locale')
 * -- it does not follow app()->setLocale(). Reading it here meant a shopper who
 * had switched the storefront to Dari was still served a picker with English
 * month names in it.
 */
    private function locale(Request $request): string
    {
        $requested = $request->query('locale') ?: app()->getLocale();

        // A reader who has chosen a language keeps it. Only a caller that has
        // chosen nothing falls through to the store's own default.
        if (is_string($requested) && trim($requested) !== '') {
            return SolarHijriLocale::normalise($requested);
        }

        return SolarHijriLocale::storeDefault();
    }

    /**
     * Refuse anything outside the range this calendar is verified for.
     *
     * 422 rather than 404: the request was understood, the year simply is not
     * one this store will print. The message names the accepted range so a
     * mistyped year is obvious from the response.
     */
    private function guard(int $year, ?int $month = null): void
    {
        $min = (int) config('calendar.validated_min_year', 1399);
        $max = (int) config('calendar.validated_max_year', 1500);

        if ($year < $min || $year > $max) {
            throw new UnprocessableEntityHttpException(
                (new UnsupportedCalendarYear($year, $min, $max))->getMessage(),
            );
        }

        if ($month !== null && ($month < 1 || $month > 12)) {
            throw new UnprocessableEntityHttpException(
                (new InvalidCalendarMonth($month))->getMessage(),
            );
        }
    }
}
