<?php

namespace Cartxis\Calendar\Support;

/**
 * Month and weekday names in Dari, Pashto and English.
 *
 * ------------------------------------------------------------------------
 * WHY THIS TABLE EXISTS AT ALL
 * ------------------------------------------------------------------------
 * PHP's Intl and JavaScript's Intl.DateTimeFormat can both convert a date to
 * the Solar Hijri calendar. Neither of them is any use to this store, because
 * both hand back IRANIAN names: Mehr, Aban, Azar, Dey, Bahman, Esfand from
 * month seven onwards. Afghan usage is different.
 *
 * Afghan Dari uses the Persian-derived names throughout -- Hamal, Sartal, Jawzal,
 * Asad, Saratan, Asban, Miqr, Aqrab, Qaws, Jadda, Ushtur, Isfand are the
 * classical forms, and the modern forms below are the ones people read today.
 * Iranian Persian has drifted further away, and its calendar also starts its
 * year on a different day by astronomical reckoning, so a locale switch alone
 * cannot give an Afghan store an Afghan date.
 *
 * So the names come from here, and the arithmetic comes from SolarHijri. The
 * two are deliberately kept apart: change a name without touching a date.
 *
 * ------------------------------------------------------------------------
 * THE LOCALES
 * ------------------------------------------------------------------------
 *   'en'  English transliteration.          Hamal, Sawr, Jawza, Saratan,
 *   'fa'  Dari, Persian-Arabic script.        Asad, Sunbula, Mizan, Aqrab,
 *   'ps'  Pashto, Persian-Arabic script.      Qaws, Jadi, Dalwa, Hoot
 *   'fa_alt'
 *         Dari alias. Kept so a stored
 *         'fa_alt' preference keeps working;
 *         it resolves to the same Afghan names.
 *
 * These are names for DISPLAY. They are not lang/*.json keys and they never
 * were: month names are proper nouns in a calendar, they are the same in an
 * invoice as on a product page, and routing them through the translation
 * dictionaries would mean a missing dictionary silently printing "Month 6".
 * A locale with no names of its own falls back to 'en' rather than printing a
 * number in a place where a word belongs.
 *
 * Every set here can be replaced from config/calendar.php without touching
 * code, and assertTranslationsAreComplete() in the test suite checks the sets
 * all have twelve months and seven days.
 */
final class SolarHijriLocale
{
    /**
     * Month names, months 1 (Farvardin) to 12 (Esfand).
     *
     * @var array<string, list<string>>
     */
    private const MONTHS = [
        'en' => [
            'Hamal',
            'Sawr',
            'Jawza',
            'Saratan',
            'Asad',
            'Sunbula',
            'Mizan',
            'Aqrab',
            'Qaws',
            'Jadi',
            'Dalwa',
            'Hoot',
        ],
        'fa' => [
            'حمل',
            'ثور',
            'جوزا',
            'سرطان',
            'اسد',
            'سنبله',
            'میزان',
            'عقرب',
            'قوس',
            'جدی',
            'دلو',
            'حوت',
        ],
        'fa_alt' => [
            'حمل',
            'ثور',
            'جوزا',
            'سرطان',
            'اسد',
            'سنبله',
            'میزان',
            'عقرب',
            'قوس',
            'جدی',
            'دلو',
            'حوت',
        ],
        'ps' => [
            'وری',
            'غویی',
            'غبرګولی',
            'چنګاښ',
            'زمری',
            'وږی',
            'تله',
            'لړم',
            'لیندۍ',
            'مرغومی',
            'سلواغه',
            'کب',
        ],
    ];

    /**
     * Weekday names, index 0 = Sunday through 6 = Saturday.
     *
     * The numbering is the one PHP's date('w') and JavaScript's Date.getDay()
     * already use, so nothing has to be translated on the way to a calendar
     * grid.
     *
     * @var array<string, list<string>>
     */
    private const WEEKDAYS = [
        'en' => [
            'Sunday',
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday',
        ],
        'fa' => [
            'یکشنبه',
            'دوشنبه',
            'سه‌شنبه',
            'چهارشنبه',
            'پنج‌شنبه',
            'جمعه',
            'شنبه',
        ],
        'fa_alt' => [
            'یکشنبه',
            'دوشنبه',
            'سه‌شنبه',
            'چهارشنبه',
            'پنج‌شنبه',
            'جمعه',
            'شنبه',
        ],
        'ps' => [
            'یکشنبه',
            'دوشنبه',
            'سه‌شنبه',
            'چهارشنبه',
            'پنجشنبه',
            'جمعه',
            'شنبه',
        ],
    ];

    /**
     * Short weekday names, for a date picker's narrow header row.
     *
     * @var array<string, list<string>>
     */
    private const WEEKDAYS_SHORT = [
        'en' => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
        'fa' => ['یک', 'دو', 'سه', 'چهار', 'پنج', 'جمعه', 'شنبه'],
        'fa_alt' => ['یک', 'دو', 'سه', 'چهار', 'پنج', 'جمعه', 'شنبه'],
        'ps' => ['یک', 'دو', 'سه', 'څوار', 'پنج', 'جمعه', 'شنبه'],
    ];

    /**
     * Month names, transliterated to ASCII for use in URLs and file names.
     *
     * @var list<string>
     */
    private const MONTHS_SLUG = [
        'farvardin',
        'ordibehesht',
        'khordad',
        'tir',
        'mordad',
        'shahrivar',
        'mehr',
        'aban',
        'azar',
        'dey',
        'bahman',
        'esfand',
    ];

    /**
     * Locale used when the requested one has no names of its own.
     */
    private const FALLBACK_LOCALE = 'en';

    /**
     * The store's own default language for dates.
     *
     * This is the answer for a caller that has not been told which language to
     * use: a queued job, an artisan command, a PDF rendered without a request,
     * a Blade template that was not handed a locale.
     *
     * It is deliberately NOT FALLBACK_LOCALE. That one is English because an
     * unknown locale is better shown in a language its reader may actually read.
     * This one is the store's decision, and this store's decision is Dari --
     * so an invoice built by a queue says 'شهریور' and the website the customer
     * is looking at says 'شهریور' too.
     *
     * config('calendar.locale') is the setting the config file documents as the
     * store default; app.locale is honoured second so a store whose whole
     * interface is English gets English dates without having to set both.
     */
    public static function storeDefault(): string
    {
        foreach (['calendar.locale', 'app.locale'] as $key) {
            $configured = CalendarConfig::get($key);

            if (is_string($configured) && trim($configured) !== '') {
                return self::normalise($configured);
            }
        }

        return self::normalise('fa');
    }

    /**
     * Normalise a locale code to one of the sets above.
     *
     * 'fa-AF', 'fa_AF', 'Dari' and 'prs' all land on 'fa'; 'ps-AF' and 'Pushto'
     * land on 'ps'. Anything unrecognised lands on the fallback, so a missing
     * or misspelt locale shows an English month name rather than nothing.
     */
    public static function normalise(?string $locale): string
    {
        $locale = strtolower(trim((string) $locale));

        return match (true) {
            $locale === '' => self::FALLBACK_LOCALE,
            $locale === 'fa_alt' => 'fa_alt',
            str_starts_with($locale, 'fa'), str_starts_with($locale, 'prs'), str_contains($locale, 'dari')
                => 'fa',
            str_starts_with($locale, 'ps'), str_starts_with($locale, 'pus'), str_contains($locale, 'pashto')
                => 'ps',
            str_starts_with($locale, 'en') => 'en',
            default => self::FALLBACK_LOCALE,
        };
    }

    /**
     * The locale names will actually be taken from.
     *
     * Worth logging in a bug report: 'fa-AF' and 'Dari' both come back as
     * 'fa', so a complaint about the wrong month name has something to chase.
     */
    public static function resolve(?string $locale): string
    {
        return self::normalise($locale);
    }

    /**
     * The name of a month, 1 to 12.
     */
    public static function monthName(int $jalaliMonth, ?string $locale = null): string
    {
        $names = self::monthNames($locale);

        return $names[$jalaliMonth - 1] ?? '';
    }

    /**
     * Every month name for a locale, months 1 to 12.
     *
     * @return list<string>
     */
    public static function monthNames(?string $locale = null): array
    {
        $locale = self::normalise($locale);

        return self::MONTHS[$locale] ?? self::MONTHS[self::FALLBACK_LOCALE];
    }

    /**
     * The ASCII name of a month, for a URL or a file name.
     */
    public static function monthSlug(int $jalaliMonth): string
    {
        return self::MONTHS_SLUG[$jalaliMonth - 1] ?? '';
    }

    /**
     * The name of a weekday, 0 = Sunday through 6 = Saturday.
     */
    public static function weekdayName(int $weekday, ?string $locale = null, bool $short = false): string
    {
        $names = $short
            ? self::weekdayNames($locale, true)
            : self::weekdayNames($locale);

        return $names[$weekday] ?? '';
    }

    /**
     * Every weekday name for a locale, index 0 = Sunday.
     *
     * @return list<string>
     */
    public static function weekdayNames(?string $locale = null, bool $short = false): array
    {
        $locale = self::normalise($locale);

        if ($short) {
            return self::WEEKDAYS_SHORT[$locale] ?? self::WEEKDAYS_SHORT[self::FALLBACK_LOCALE];
        }

        return self::WEEKDAYS[$locale] ?? self::WEEKDAYS[self::FALLBACK_LOCALE];
    }

    /**
     * The whole table, for a client that wants to render a calendar itself.
     *
     * @return array{locale: string, months: list<string>, weekdays: list<string>, weekdaysShort: list<string>}
     */
    public static function table(?string $locale = null): array
    {
        return [
            'locale' => self::normalise($locale),
            'months' => self::monthNames($locale),
            'weekdays' => self::weekdayNames($locale),
            'weekdaysShort' => self::weekdayNames($locale, true),
        ];
    }
}
