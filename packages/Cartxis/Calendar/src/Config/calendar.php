<?php

return [

    /*
    |--------------------------------------------------------------------------
    | The default language
    |--------------------------------------------------------------------------
    |
    | The store's default is Dari. This is the locale used whenever a request
    | has not chosen one -- an unrecognised Accept-Language, a direct hit on a
    | PDF link, an artisan command. It is NOT a fallback that overrides a
    | reader's own choice: someone who has picked English gets English month
    | names.
    |
    */

    'locale' => env('CALENDAR_LOCALE', 'fa'),

    /*
    |--------------------------------------------------------------------------
    | Which calendar leads, and which one follows
    |--------------------------------------------------------------------------
    |
    | The store's permanent decision, in one place:
    |
    |   primary   'solar'      -- the Solar Hijri date is the real one
    |   secondary 'gregorian'  -- the Gregorian date follows in brackets
    |
    | That renders as:  ۱۵ شهریور ۱۴۰۴ (6 September 2025)
    |
    | Setting 'secondary' to 'none' gives the Solar Hijri date alone, which is
    | what a table column wants when every row already shows the year.
    |
    | Both are resolved here rather than at each of the 29 date inputs and 45
    | Vue files, because a store that shows the two calendars in a different
    | order on two different screens looks broken even though both are right.
    |
    */

    'primary' => env('CALENDAR_PRIMARY', 'solar'),

    'secondary' => env('CALENDAR_SECONDARY', 'gregorian'),

    /*
    |--------------------------------------------------------------------------
    | Numerals
    |--------------------------------------------------------------------------
    |
    |   'fa'    Persian (extended Arabic-Indic, U+06F0-06F9). The default, and
    |            what a Dari date reads as.
    |   'arab'  Arabic-Indic (U+0660-0669). The Arabic-script set.
    |   'latn'  Latin '0'-'9'.
    |
    | The two halves of a displayed date can differ, and here they do: the
    | primary (Solar Hijri) uses Persian numerals because it is the half in the
    | reader's own script, while the bracketed Gregorian reference stays Latin
    | because a reference is most useful when it can be copied into a bank form
    | or a spreadsheet without retyping it.
    |
    | Decided once, globally, because an invoice that reads ۱۵ سنبله ۱۴۰۴ while
    | the email it was sent as read 15 Shahrivar 1404 is an inconsistency a
    | customer notices straight away.
    |
    | Note this affects DATES ONLY. Order numbers, phone numbers, tracking
    | codes and email addresses keep their Latin digits -- a digit-shaped ID
    | that changes script is no longer searchable.
    |
    */

    'numerals' => env('CALENDAR_NUMERALS', 'fa'),

    'secondary_numerals' => env('CALENDAR_SECONDARY_NUMERALS', 'latn'),

    /*
    |--------------------------------------------------------------------------
    | How the two calendars are bracketed together
    |--------------------------------------------------------------------------
    |
    | The wrapper around the secondary date. The {secondary} placeholder is
    | replaced; anything else is left alone, so '({secondary})' becomes
    | '(6 September 2025)' and '· {secondary}' would become a separator.
    |
    | The brackets are doing real work: they tell a reader who knows both
    | calendars that the two dates are one day described twice, and they keep
    | the store usable for someone who reads only Gregorian -- a card
    | statement, a customs form, an accountant in another country.
    |
    */

    'bracket' => env('CALENDAR_BRACKET', '({secondary})'),

    /*
    |--------------------------------------------------------------------------
    | The range the engine is trusted for
    |--------------------------------------------------------------------------
    |
    | Jalali 1399-1500 is Gregorian 2020-03-20 to 2122-03-20, which covers
    | every date an order, invoice or subscription can realistically reach.
    |
    | The arithmetic itself is pure and will answer for any year inside
    | MIN_YEAR..MAX_YEAR, but only the range in the middle has been checked
    | day by day. Outside it, dates come back rather than throwing, so a
    | mistyped year shows a wrong date instead of an error -- which is why
    | input is validated against the middle range, not the outer one.
    |
    */

    'min_year' => 1399,

    'max_year' => 1500,

    'validated_min_year' => 1399,

    'validated_max_year' => 1500,

    'engine_min_year' => 1178,

    'engine_max_year' => 1634,

    /*
    |--------------------------------------------------------------------------
    | Week layout
    |--------------------------------------------------------------------------
    |
    | The Afghan week starts on Saturday. In a right-to-left layout that also
    | puts the first column on the right, where a Dari or Pashto reader expects
    | Sunday to be, so one ordering serves everyone.
    |
    */

    'week_starts_on' => 6,

    /*
    |--------------------------------------------------------------------------
    | Month and weekday names
    |--------------------------------------------------------------------------
    |
    | Defaults to the built-in tables in SolarHijriLocale, which hold Dari,
    | Dari-with-classical-variants, Pashto and English sets.
    |
    | They are NOT lang/*.json keys, and deliberately so. A month name is a
    | proper noun in a calendar: it is the same word in an invoice as on a
    | product page, it does not translate per feature, and routing it through
    | the translation dictionaries would mean a missing dictionary silently
    | printing "Month 6" on a customer's invoice. A locale with no names of its
    | own falls back to English rather than printing a number where a word
    | belongs.
    |
    | Override here if a native speaker wants a different set -- the most
    | common candidate being fa_alt, which uses the classical/regional forms
    | people say out loud (Sumbul for Shahrivar being the obvious one).
    |
    | Shape: ['fa' => [12 month names...], ...] keyed by locale.
    |
    */

    'months' => [
        // 'fa' => [...], 'fa_alt' => [...], 'ps' => [...], 'en' => [...],
    ],

    'weekdays' => [
        // 'fa' => [...7, Sunday first...], 'ps' => [...], 'en' => [...],
    ],

];
