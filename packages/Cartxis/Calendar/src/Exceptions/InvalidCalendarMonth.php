<?php

namespace Cartxis\Calendar\Exceptions;

use InvalidArgumentException;

/**
 * A month number outside 1-12.
 *
 * 1 is Farvardin, the first month of the Solar Hijri year, and 12 is Esfand,
 * the last. There is no month 0 and no month 13, and a request that says
 * otherwise is a bug somewhere upstream -- so it is named as one.
 */
class InvalidCalendarMonth extends InvalidArgumentException
{
    public function __construct(public readonly int $month)
    {
        parent::__construct(sprintf(
            'Solar Hijri month must be between 1 (Farvardin) and 12 (Esfand), got %d.',
            $month,
        ));
    }
}
