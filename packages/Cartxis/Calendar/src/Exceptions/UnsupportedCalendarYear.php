<?php

namespace Cartxis\Calendar\Exceptions;

use InvalidArgumentException;

/**
 * A year outside the range this calendar has been checked for.
 *
 * The arithmetic inside SolarHijri can answer for any year between 1178 and
 * 1634. It should not be trusted outside 1399-1500 (Gregorian 2020-2122), which
 * is the span verified day by day against an independent implementation.
 *
 * Outside that span the engine still returns a plausible-looking date rather
 * than throwing -- a date is not wrong in an obvious way, it is just wrong. So
 * anything taking input from a person refuses it up front instead, and says
 * which range it will accept.
 */
class UnsupportedCalendarYear extends InvalidArgumentException
{
    public function __construct(
        public readonly int $year,
        public readonly int $minYear,
        public readonly int $maxYear,
    ) {
        parent::__construct(sprintf(
            'Solar Hijri year %d is outside the range this calendar is verified for, '
            .'%d to %d. Dates outside it are not safe to show a customer.',
            $year,
            $minYear,
            $maxYear,
        ));
    }
}
