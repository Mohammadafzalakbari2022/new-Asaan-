{{--
    A whole Solar Hijri month as a table.

    THE ONE RULE ABOUT THIS PARTIAL
    -------------------------------
    Everything printed here is for a person to read. The Gregorian value
    behind each Solar Hijri day is carried along in a data- attribute and in a
    hidden input, because that -- never the Solar Hijri figure -- is what must
    reach the database when the form is submitted. Nothing in this file may be
    used as a sortable or comparable value.

    That is why each cell carries both: the shopper reads ۱۵ شهریور, and the
    server receives 2025-09-06.

    Usage:

        @include('calendar::month', ['date' => $order->created_at])
        @include('calendar::month', ['date' => $now, 'locale' => 'en'])

    Parameters:

        date     mixed  required  any date-shaped value; the month it falls in
                             is the month shown
        locale   string optional 'fa' (default), 'fa_alt', 'ps' or 'en'.
                             Defaults to the request's language.
        editable bool   optional when true, each in-month day is a radio button
                             carrying its GREGORIAN value as the form value
        name     string optional form field name, required when editable
--}}
@php
    $calendarLocale = $locale ?? app()->getLocale();
    $month = \Cartxis\Calendar\Support\SolarHijriCalendar::monthFor($date, $calendarLocale);
    $isEditable = (bool) ($editable ?? false);
    $fieldName = $name ?? 'date';
    $todayGregorian = now('Asia/Kabul')->toDateString();
@endphp

<div class="solar-hijri-calendar" dir="rtl" lang="{{ $month['weekdays'] ? 'fa' : 'en' }}">
    <table class="solar-hijri-calendar__grid">
        <caption class="solar-hijri-calendar__title">
            {{ $month['monthName'] }} {{ \Cartxis\Calendar\Support\PersianDigits::to($month['year']) }}
            @if ($month['isLeapYear'])
                <span class="solar-hijri-calendar__leap">{{ \Cartxis\Calendar\Support\PersianDigits::to('(leap year)') }}</span>
            @endif
        </caption>

        <thead>
            <tr>
                @foreach ($month['weekdays'] as $weekday)
                    <th scope="col" abbr="{{ $weekday }}">{{ $weekday }}</th>
                @endforeach
            </tr>
        </thead>

        <tbody>
            @foreach ($month['weeks'] as $week)
                <tr>
                    @foreach ($week as $cell)
                        <td class="solar-hijri-calendar__day @if (! $cell['inMonth']) solar-hijri-calendar__day--outside @endif @if ($cell['gregorian'] === $todayGregorian) solar-hijri-calendar__day--today @endif"
                            data-solar="{{ $cell['iso'] }}"
                            data-gregorian="{{ $cell['gregorian'] }}">
                            @if ($isEditable && $cell['inMonth'])
                                {{--
                                    The value carried into the form is the
                                    GREGORIAN date. Unchanged, unconverted,
                                    exactly as it will be stored.
                                --}}
                                <label>
                                    <input type="radio"
                                           name="{{ $fieldName }}"
                                           value="{{ $cell['gregorian'] }}"
                                           @checked(old($fieldName, '') === $cell['gregorian'])>
                                    <span>{{ \Cartxis\Calendar\Support\PersianDigits::to($cell['day']) }}</span>
                                </label>
                            @else
                                <time datetime="{{ $cell['gregorian'] }}"
                                      title="{{ solar_hijri($cell['gregorian'], $calendarLocale) }}">
                                    {{ \Cartxis\Calendar\Support\PersianDigits::to($cell['day']) }}
                                </time>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
