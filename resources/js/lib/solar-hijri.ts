/**
 * The Solar Hijri (Afghan Jalali / Shamsi) calendar, in the browser.
 *
 * ------------------------------------------------------------------------
 * READ THIS BEFORE CALLING ANYTHING IN HERE
 * ------------------------------------------------------------------------
 * This module is FOR HUMAN DISPLAY ONLY.
 *
 * Nothing here touches a database, an API payload or a payment request. Every
 * date the app stores and sends over the wire is ISO-8601 Gregorian, and it
 * must stay that way: the server has to be able to sort, filter and compare it
 * in SQL, and every other system has to understand it.
 *
 * So the rule for the browser is the same as for PHP: convert at the moment
 * you are about to PRINT a date, and nowhere else. In particular, the value
 * that leaves a date input must be the GREGORIAN one. formatDate() is for
 * labels; toIso() and toParts() are for carrying a value through.
 *
 * ------------------------------------------------------------------------
 * WHY THERE ARE TWO COPIES OF THIS FILE
 * ------------------------------------------------------------------------
 * The server has its own implementation in
 * packages/Cartxis/Calendar/src/Support/SolarHijri.php. This file is a line
 * for line twin of it, and that is deliberate: an admin screen and the invoice
 * emailed from the order behind it must never disagree by a day.
 *
 * Two copies of a calendar is a real risk -- they drift. So they are proven
 * not to drift, against one shared table of dates that neither of them wrote:
 * resources/js/lib/solar-hijri.test.ts runs every vector in
 * solar-hijri-vectors.json through this file, and the PHP suite runs the same
 * vectors through the PHP one. If either implementation is changed without the
 * other, one of the two suites goes red.
 *
 * Keep this file and its PHP twin in step. Change the leap rule in one and the
 * tests will tell you immediately.
 *
 * ------------------------------------------------------------------------
 * THE ARITHMETIC
 * ------------------------------------------------------------------------
 * A solar year begins at the vernal equinox, so it has no fixed relationship
 * to Gregorian months. Two facts place any date:
 *
 *   1. The Solar Hijri year holding a Gregorian date is (Gregorian year - 621)
 *      or one earlier. The exact start is found by counting whole years from a
 *      single known anchor.
 *
 *   2. Every year is 365 days, or 366 in a leap year. Leap years fall eight
 *      times in a repeating 33-year cycle, when the remainder of
 *      (year mod 33) is one of 1, 5, 9, 13, 17, 22, 26, 30. The second half of
 *      the cycle is not evenly spaced -- after remainder 17 comes remainder 22,
 *      a five-year gap, then four-year gaps -- and that irregularity is what
 *      keeps the calendar honest against the real length of the solar year.
 *
 * THE ANCHOR is 1 Farvardin 1400 == 21 March 2021.
 *
 * The Gregorian side is plain integer arithmetic (days_from_civil and
 * civil_from_days), with no Date object, no timezone and no daylight saving
 * anywhere in it. That is on purpose: Date.UTC is fine for these years, but it
 * is not fine for "the same instant" reasoning, and a DST boundary must never
 * be able to move a date by one day.
 *
 * Verified and tested for Jalali 1399-1500 (Gregorian 2020-2122).
 */

export type SolarLocale = 'fa' | 'fa_alt' | 'ps' | 'en';

export type Numerals = 'fa' | 'arab' | 'latn';

/** Which calendar leads the line. The store's default is Solar Hijri. */
export type PrimaryCalendar = 'solar' | 'gregorian';

/** Which calendar follows it, or 'none' for no second date at all. */
export type SecondaryCalendar = PrimaryCalendar | 'none';

export interface SolarDate {
    year: number;
    month: number;
    day: number;
}

export interface SolarDateParts extends SolarDate {
    /** 'YYYY-MM-DD' in the SOLAR calendar. For a picker's label, not storage. */
    iso: string;
    /** 'YYYY-MM-DD' GREGORIAN. This is the value that gets stored. */
    gregorian: string;
    /** 0 = Sunday through 6 = Saturday. */
    weekday: number;
}

export interface FormatOptions {
    primary?: PrimaryCalendar;
    secondary?: SecondaryCalendar;
    /** Numerals for the primary half. */
    numerals?: Numerals;
    /** Numerals for the secondary half. */
    secondaryNumerals?: Numerals;
    weekday?: boolean;
    short?: boolean;
    /** A template with a {secondary} placeholder. Default '({secondary})'. */
    bracket?: string;
}

export interface GridDay {
    day: number;
    month: number;
    year: number;
    iso: string;
    gregorian: string;
    weekday: number;
    inMonth: boolean;
}

export interface MonthGrid {
    year: number;
    month: number;
    isLeapYear: boolean;
    daysInMonth: number;
    monthName: string;
    weekdays: string[];
    weeks: GridDay[][];
}

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

/** The earliest Solar Hijri year this engine will answer for. About 1799 AD. */
export const MIN_JALALI_YEAR = 1178;

/** The latest Solar Hijri year this engine will answer for. About 2255 AD. */
export const MAX_JALALI_YEAR = 1634;

/** The year everything is counted from, and the Gregorian date it is. */
export const ANCHOR_JALALI_YEAR = 1400;

export const ANCHOR_GREGORIAN = '2021-03-21';

/**
 * The remainders of (year mod 33) that make a Solar Hijri leap year.
 *
 * Eight years in every thirty-three, which works out at 365.2424 days a year.
 */
export const LEAP_REMAINDERS: readonly number[] = [1, 5, 9, 13, 17, 22, 26, 30];

/** Weekday index that starts a week: 6 = Saturday, where the Afghan week does. */
export const WEEK_STARTS_ON = 6;

const MONTHS: Record<SolarLocale, string[]> = {
    en: [
        'Farvardin',
        'Ordibehesht',
        'Khordad',
        'Tir',
        'Mordad',
        'Shahrivar',
        'Mehr',
        'Aban',
        'Azar',
        'Dey',
        'Bahman',
        'Esfand',
    ],
    fa: [
        'فروردین',
        'اردیبهشت',
        'خرداد',
        'تیر',
        'مرداد',
        'شهریور',
        'مهر',
        'آبان',
        'آذر',
        'دی',
        'بهمن',
        'اسفند',
    ],
    fa_alt: [
        'فروردین',
        'اردیبهشت',
        'خرداد',
        'تیر',
        'مرداد',
        'سنبله',
        'مهر',
        'آبان',
        'آذر',
        'دی',
        'بهمن',
        'اسفند',
    ],
    ps: [
        'فرورډین',
        'اورديبهشت',
        'خورداد',
        'تیر',
        'مرداد',
        'شېربوار',
        'مهر',
        'اګبون',
        'آذر',
        'دی',
        'بهمن',
        'اسفند',
    ],
};

const WEEKDAYS: Record<SolarLocale, string[]> = {
    en: [
        'Sunday',
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
    ],
    fa: ['یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه', 'شنبه'],
    fa_alt: ['یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه', 'شنبه'],
    ps: ['یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه'],
};

const WEEKDAYS_SHORT: Record<SolarLocale, string[]> = {
    en: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
    fa: ['یک', 'دو', 'سه', 'چهار', 'پنج', 'جمعه', 'شنبه'],
    fa_alt: ['یک', 'دو', 'سه', 'چهار', 'پنج', 'جمعه', 'شنبه'],
    ps: ['یک', 'دو', 'سه', 'څوار', 'پنج', 'جمعه', 'شنبه'],
};

const MONTHS_SLUG: readonly string[] = [
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

/** English month names, for the bracketed Gregorian reference. */
const GREGORIAN_MONTHS: readonly string[] = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
];

const GREGORIAN_MONTHS_SHORT: readonly string[] = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
];

const PERSIAN_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

const ARABIC_DIGITS = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

const PERSIAN_INVERSE: Record<string, string> = {};
PERSIAN_DIGITS.forEach((digit, index) => {
    PERSIAN_INVERSE[digit] = String(index);
});

const ARABIC_INVERSE: Record<string, string> = {};
ARABIC_DIGITS.forEach((digit, index) => {
    ARABIC_INVERSE[digit] = String(index);
});

// ---------------------------------------------------------------------------
// Memoised year starts
// ---------------------------------------------------------------------------

let anchorSerialDay: number | null = null;

const nowruzCache = new Map<number, number>();

const yearLengthCache = new Map<number, number>();

// ---------------------------------------------------------------------------
// The 33-year leap rule
// ---------------------------------------------------------------------------

/** (value mod 33), always answering 0-32 even for a negative value. */
function mod33(value: number): number {
    const remainder = value % 33;

    return remainder < 0 ? remainder + 33 : remainder;
}

/** Is this Solar Hijri year a leap year of 366 days? */
export function isLeapYear(jalaliYear: number): boolean {
    return LEAP_REMAINDERS.includes(mod33(jalaliYear));
}

/** Every leap year from `from` to `to` inclusive. */
export function leapYears(from: number, to: number): number[] {
    const years: number[] = [];

    for (let year = from; year <= to; year++) {
        if (isLeapYear(year)) {
            years.push(year);
        }
    }

    return years;
}

/** How many days this Solar Hijri year contains. */
export function daysInYear(jalaliYear: number): number {
    const cached = yearLengthCache.get(jalaliYear);
    if (cached !== undefined) {
        return cached;
    }

    const length = isLeapYear(jalaliYear) ? 366 : 365;
    yearLengthCache.set(jalaliYear, length);

    return length;
}

// ---------------------------------------------------------------------------
// Months
// ---------------------------------------------------------------------------

/**
 * How many days are in this Solar Hijri month.
 *
 * Months 1-6 run 31 days, 7-11 run 30, and Esfand runs 29 -- or 30 in a leap
 * year. That extra day at the end of Esfand IS the leap day of this calendar;
 * there is no other one in the year.
 */
export function daysInMonth(jalaliYear: number, jalaliMonth: number): number {
    assertMonth(jalaliMonth);

    if (jalaliMonth <= 6) {
        return 31;
    }

    if (jalaliMonth <= 11) {
        return 30;
    }

    return isLeapYear(jalaliYear) ? 30 : 29;
}

/** Month lengths for a whole year, months 1 to 12. */
export function monthLengths(jalaliYear: number): number[] {
    return Array.from({ length: 12 }, (_, index) =>
        daysInMonth(jalaliYear, index + 1),
    );
}

// ---------------------------------------------------------------------------
// New Year
// ---------------------------------------------------------------------------

function getAnchorSerialDay(): number {
    if (anchorSerialDay === null) {
        anchorSerialDay = serialDay(2021, 3, 21);
    }

    return anchorSerialDay;
}

function closestCachedYear(target: number): number {
    let closest = ANCHOR_JALALI_YEAR;
    let smallestGap = Number.POSITIVE_INFINITY;

    for (const year of nowruzCache.keys()) {
        const gap = Math.abs(year - target);

        if (gap < smallestGap) {
            smallestGap = gap;
            closest = year;
        }
    }

    return closest;
}

function nowruzSerialDay(jalaliYear: number): number {
    assertYear(jalaliYear);

    const cached = nowruzCache.get(jalaliYear);
    if (cached !== undefined) {
        return cached;
    }

    nowruzCache.set(ANCHOR_JALALI_YEAR, getAnchorSerialDay());

    let start = closestCachedYear(jalaliYear);
    let serialDay = nowruzCache.get(start) as number;

    // One year at a time towards the year we want, remembering each step. A
    // single addition in the common case.
    while (start !== jalaliYear) {
        if (start < jalaliYear) {
            serialDay += daysInYear(start);
            start += 1;
        } else {
            start -= 1;
            serialDay -= daysInYear(start);
        }

        nowruzCache.set(start, serialDay);
    }

    return serialDay;
}

/** The Gregorian 'Y-m-d' of 1 Farvardin -- Solar New Year, Nowruz. */
export function nowruzGregorian(jalaliYear: number): string {
    const [year, month, day] = civilFromSerialDay(nowruzSerialDay(jalaliYear));

    return iso(year, month, day);
}

/** The day in March Nowruz falls on: 21, or 20 in a leap year. */
export function nowruzMarchDay(jalaliYear: number): number {
    return Number(nowruzGregorian(jalaliYear).slice(-2));
}

// ---------------------------------------------------------------------------
// Conversion
// ---------------------------------------------------------------------------

function assertYear(jalaliYear: number): void {
    if (
        jalaliYear >= MIN_JALALI_YEAR &&
        jalaliYear <= MAX_JALALI_YEAR
    ) {
        return;
    }

    throw new RangeError(
        `Solar Hijri year ${jalaliYear} is outside the supported range ${MIN_JALALI_YEAR}-${MAX_JALALI_YEAR}.`,
    );
}

function assertMonth(jalaliMonth: number): void {
    if (jalaliMonth < 1 || jalaliMonth > 12) {
        throw new RangeError(
            `Solar Hijri month must be 1-12, got ${jalaliMonth}.`,
        );
    }
}

/** Does this Solar Hijri date actually exist? */
export function isValid(jalaliYear: number, jalaliMonth: number, jalaliDay: number): boolean {
    if (jalaliYear < MIN_JALALI_YEAR || jalaliYear > MAX_JALALI_YEAR) {
        return false;
    }

    if (jalaliMonth < 1 || jalaliMonth > 12 || jalaliDay < 1) {
        return false;
    }

    return jalaliDay <= daysInMonth(jalaliYear, jalaliMonth);
}

/** The Gregorian day number for a Solar Hijri date. */
function serialDayFromSolar(
    jalaliYear: number,
    jalaliMonth: number,
    jalaliDay: number,
): number {
    assertYear(jalaliYear);
    assertMonth(jalaliMonth);
    assertValid(jalaliYear, jalaliMonth, jalaliDay);

    return nowruzSerialDay(jalaliYear) + dayOfYear(jalaliYear, jalaliMonth, jalaliDay) - 1;
}

/**
 * Which day of the Solar Hijri year a date is: 1 for 1 Farvardin, and 365 or
 * 366 for the last day of Esfand.
 */
export function dayOfYear(
    jalaliYear: number,
    jalaliMonth: number,
    jalaliDay: number,
): number {
    assertMonth(jalaliMonth);
    assertValid(jalaliYear, jalaliMonth, jalaliDay);

    let total = jalaliDay;

    for (let month = 1; month < jalaliMonth; month++) {
        total += daysInMonth(jalaliYear, month);
    }

    return total;
}

function fromDayOfYear(jalaliYear: number, dayOfYear: number): SolarDate {
    let remaining = dayOfYear;

    for (let month = 1; month <= 12; month++) {
        const length = daysInMonth(jalaliYear, month);

        if (remaining <= length) {
            return { year: jalaliYear, month, day: remaining };
        }

        remaining -= length;
    }

    throw new RangeError(
        `Day ${dayOfYear} does not exist in Solar Hijri year ${jalaliYear}.`,
    );
}

/**
 * Split anything date-shaped into proleptic Gregorian year, month, day.
 *
 * The time of day is IGNORED -- this calendar has no clock, so two timestamps
 * on the same day give the same answer.
 */
function gregorianParts(date: Date | string | number): [number, number, number] {
    if (date instanceof Date) {
        return [
            date.getUTCFullYear(),
            date.getUTCMonth() + 1,
            date.getUTCDate(),
        ];
    }

    if (typeof date === 'number') {
        const asDate = new Date(date);

        return [
            asDate.getUTCFullYear(),
            asDate.getUTCMonth() + 1,
            asDate.getUTCDate(),
        ];
    }

    const trimmed = date.trim();

    // The fast path: a leading ISO-8601 date, no Date object involved. This is
    // what every date arriving from an API payload looks like.
    const isoMatch = trimmed.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (isoMatch) {
        return [Number(isoMatch[1]), Number(isoMatch[2]), Number(isoMatch[3])];
    }

    const parsed = new Date(trimmed);
    if (Number.isNaN(parsed.getTime())) {
        throw new TypeError(`Could not read a Gregorian date from [${trimmed}].`);
    }

    return [
        parsed.getUTCFullYear(),
        parsed.getUTCMonth() + 1,
        parsed.getUTCDate(),
    ];
}

/** The Solar Hijri date for a Gregorian one. */
export function fromGregorian(date: Date | string | number): SolarDate {
    const [year, month, day] = gregorianParts(date);

    let jalaliYear = year - 621;
    const serialDay = serialDay(year, month, day);

    if (serialDay < nowruzSerialDay(jalaliYear)) {
        jalaliYear -= 1;
    }

    return fromDayOfYear(jalaliYear, serialDay - nowruzSerialDay(jalaliYear) + 1);
}

/** The Gregorian 'Y-m-d' matching a Solar Hijri date. */
export function toGregorianIso(
    jalaliYear: number,
    jalaliMonth: number,
    jalaliDay: number,
): string {
    const [year, month, day] = civilFromSerialDay(
        serialDayFromSolar(jalaliYear, jalaliMonth, jalaliDay),
    );

    return iso(year, month, day);
}

/**
 * The Solar Hijri date, the Gregorian date, and the weekday, all at once.
 *
 * What a date picker needs: what to show the shopper, and what to send back to
 * the server so the stored value is Gregorian.
 */
export function toParts(date: Date | string | number): SolarDateParts {
    const solar = fromGregorian(date);
    const gregorian = toGregorianIso(solar.year, solar.month, solar.day);

    return {
        ...solar,
        iso: toSolarIso(solar),
        gregorian,
        weekday: weekdayOfGregorian(gregorian),
    };
}

/**
 * The Solar Hijri date as 'YYYY-MM-DD' -- for a picker's label, never a column.
 */
export function toSolarIso(date: Date | string | number | SolarDate): string {
    const solar = isSolarDate(date) ? date : fromGregorian(date);

    return iso(solar.year, solar.month, solar.day);
}

/** Is this a SolarDate object rather than a Gregorian input? */
export function isSolarDate(value: unknown): value is SolarDate {
    return (
        typeof value === 'object' &&
        value !== null &&
        typeof (value as SolarDate).year === 'number' &&
        typeof (value as SolarDate).month === 'number' &&
        typeof (value as SolarDate).day === 'number'
    );
}

// ---------------------------------------------------------------------------
// Moving around
// ---------------------------------------------------------------------------

/** The Solar Hijri date N days after (or before) a Gregorian one. */
export function addDays(date: Date | string | number, days: number): SolarDate {
    const [year, month, day] = gregorianParts(date);
    const [targetYear, targetMonth, targetDay] = civilFromSerialDay(
        serialDay(year, month, day) + days,
    );

    return fromGregorian(iso(targetYear, targetMonth, targetDay));
}

/** 1970-01-01 was a Thursday, which is index 4. */
function weekdayFromSerialDay(dayNumber: number): number {
    return (((dayNumber + 4) % 7) + 7) % 7;
}

/** The weekday of a Gregorian date, 0 = Sunday through 6 = Saturday. */
export function weekdayOfGregorian(date: Date | string | number): number {
    const [year, month, day] = gregorianParts(date);

    return weekdayFromSerialDay(serialDay(year, month, day));
}

/** The weekday of a Solar Hijri date, 0 = Sunday through 6 = Saturday. */
export function weekdayOfSolar(
    jalaliYear: number,
    jalaliMonth: number,
    jalaliDay: number,
): number {
    return weekdayFromSerialDay(serialDayFromSolar(jalaliYear, jalaliMonth, jalaliDay));
}

// ---------------------------------------------------------------------------
// Gregorian arithmetic, in plain integers
// ---------------------------------------------------------------------------

/** Days since 1970-01-01 for a proleptic Gregorian date. */
function serialDay(year: number, month: number, day: number): number {
    const shiftedYear = year - (month <= 2 ? 1 : 0);

    const era = Math.floor(shiftedYear / 400);
    const yearOfEra = shiftedYear - era * 400;
    const dayOfYear =
        Math.floor((153 * (month + (month > 2 ? -3 : 9)) + 2) / 5) + day - 1;
    const dayOfEra =
        yearOfEra * 365 +
        Math.floor(yearOfEra / 4) -
        Math.floor(yearOfEra / 100) +
        dayOfYear;

    return era * 146097 + dayOfEra - 719468;
}

/** The proleptic Gregorian date a day number since 1970-01-01 lands on. */
function civilFromSerialDay(dayNumber: number): [number, number, number] {
    const z = dayNumber + 719468;

    const era = Math.floor(z / 146097);
    const dayOfEra = z - era * 146097;
    const yearOfEra = Math.floor(
        dayOfEra -
            Math.floor(dayOfEra / 1460) +
            Math.floor(dayOfEra / 36524) -
            Math.floor(dayOfEra / 146096),
        365,
    );
    const year = yearOfEra + era * 400;
    const dayOfYearValue =
        dayOfEra -
        (yearOfEra * 365 + Math.floor(yearOfEra / 4) - Math.floor(yearOfEra / 100));
    const monthPrime = Math.floor((5 * dayOfYearValue + 2) / 153);
    const day = dayOfYearValue - Math.floor((153 * monthPrime + 2) / 5) + 1;
    const month = monthPrime + (monthPrime < 10 ? 3 : -9);

    return [year + (month <= 2 ? 1 : 0), month, day];
}

/** 'Y-m-d' with a four-digit year. */
function iso(year: number, month: number, day: number): string {
    return `${String(year).padStart(4, '0')}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

// ---------------------------------------------------------------------------
// Locale names
// ---------------------------------------------------------------------------

/**
 * Normalise a locale code to one of the sets we have names for.
 *
 * 'fa-AF', 'fa_AF', 'Dari' and 'prs' all land on 'fa'; 'ps-AF' and 'Pushto' land
 * on 'ps'. Anything unrecognised lands on English, so a missing or misspelt
 * locale shows an English month name rather than nothing.
 */
export function normaliseLocale(locale: string | null | undefined): SolarLocale {
    const code = String(locale ?? '')
        .trim()
        .toLowerCase();

    if (code === '') {
        return 'en';
    }

    if (code === 'fa_alt') {
        return 'fa_alt';
    }

    if (code.startsWith('fa') || code.startsWith('prs') || code.includes('dari')) {
        return 'fa';
    }

    if (
        code.startsWith('ps') ||
        code.startsWith('pus') ||
        code.includes('pashto')
    ) {
        return 'ps';
    }

    if (code.startsWith('en')) {
        return 'en';
    }

    return 'en';
}

/** The name of a month, 1 to 12. */
export function monthName(jalaliMonth: number, locale?: string | null): string {
    const names = MONTHS[normaliseLocale(locale)];

    return names[jalaliMonth - 1] ?? '';
}

/** Every month name for a locale, months 1 to 12. */
export function monthNames(locale?: string | null): string[] {
    return [...MONTHS[normaliseLocale(locale)]];
}

/** The ASCII name of a month, for a URL or a file name. */
export function monthSlug(jalaliMonth: number): string {
    return MONTHS_SLUG[jalaliMonth - 1] ?? '';
}

/** The name of a weekday, 0 = Sunday through 6 = Saturday. */
export function weekdayName(
    weekday: number,
    locale?: string | null,
    short = false,
): string {
    const table = short ? WEEKDAYS_SHORT : WEEKDAYS;

    return table[normaliseLocale(locale)][weekday] ?? '';
}

/** Every weekday name for a locale, index 0 = Sunday. */
export function weekdayNames(locale?: string | null, short = false): string[] {
    const table = short ? WEEKDAYS_SHORT : WEEKDAYS;

    return [...table[normaliseLocale(locale)]];
}

// ---------------------------------------------------------------------------
// Numerals
// ---------------------------------------------------------------------------

/**
 * Convert the digits in a string to the requested numeral set.
 *
 * Only digits are touched: the month name sitting between them is left exactly
 * as it was, so a whole formatted date converts cleanly.
 */
export function toNumerals(
    value: string,
    numerals: Numerals = 'fa',
): string {
    if (numerals === 'latn' || !/[0-9]/.test(value)) {
        return value;
    }

    const map = numerals === 'arab' ? ARABIC_DIGITS : PERSIAN_DIGITS;

    return value.replace(/[0-9]/g, (digit) => map[Number(digit)]);
}

/**
 * The opposite: back to Latin digits.
 *
 * Needed when a shopper types ۱۴۰۴ into a search box, or when a date picked in
 * Persian digits has to be read by something that only knows ASCII.
 */
export function fromNumerals(value: string): string {
    return value.replace(/[۰-۹٠-٩]/g, (digit) => {
        const mapped = PERSIAN_INVERSE[digit] ?? ARABIC_INVERSE[digit];

        return mapped ?? digit;
    });
}

// ---------------------------------------------------------------------------
// Formatting
// ---------------------------------------------------------------------------

/**
 * Format a date as a person reads it.
 *
 * The store's permanent pairing, applied in one place so 29 date inputs and 45
 * Vue files cannot disagree: the Solar Hijri date first, the Gregorian reference
 * in brackets. '۱۵ شهریور ۱۴۰۴ (6 September 2025)'.
 *
 * The brackets are doing real work. They tell a reader who knows both calendars
 * that this is one day described twice, and they keep the store usable for
 * someone who reads only Gregorian -- a card statement, a customs form, an
 * accountant in another country -- so no screen has to grow a toggle for them.
 */
export function formatDate(
    date: Date | string | number,
    locale?: string | null,
    options: FormatOptions = {},
): string {
    const resolved = resolveOptions(options);
    const parts = toParts(date);
    const resolvedLocale = normaliseLocale(locale);

    const primary =
        resolved.primary === 'gregorian'
            ? renderGregorian(parts, resolvedLocale, resolved, resolved.numerals)
            : renderSolar(parts, resolvedLocale, resolved);

    if (resolved.secondary === 'none') {
        return primary;
    }

    const secondary =
        resolved.secondary === 'solar'
            ? renderSolar(parts, resolvedLocale, {
                  ...resolved,
                  numerals: resolved.secondaryNumerals,
              })
            : renderGregorian(parts, resolvedLocale, resolved, resolved.secondaryNumerals);

    if (secondary === '') {
        return primary;
    }

    return `${primary} ${resolved.bracket.replace('{secondary}', secondary)}`;
}

/** Just the Solar Hijri half: '۱۵ شهریور ۱۴۰۴'. */
export function formatSolar(
    date: Date | string | number,
    locale?: string | null,
    options: FormatOptions = {},
): string {
    return formatDate(date, locale, { ...options, secondary: 'none' });
}

/** Just the Gregorian half: '6 September 2025'. */
export function formatGregorian(
    date: Date | string | number,
    locale?: string | null,
    options: FormatOptions = {},
): string {
    return formatDate(date, locale, {
        ...options,
        primary: 'gregorian',
        secondary: options.secondary ?? 'none',
    });
}

/** In words, with the weekday: 'شنبه، ۱۵ شهریور ۱۴۰۴ (Saturday, 6 September 2025)'. */
export function formatLong(
    date: Date | string | number,
    locale?: string | null,
): string {
    return formatDate(date, locale, { weekday: true });
}

/** In numerals only: '۱۴۰۴/۰۶/۱۵ (2025/09/06)'. */
export function formatShort(
    date: Date | string | number,
    locale?: string | null,
    options: FormatOptions = {},
): string {
    return formatDate(date, locale, { ...options, short: true });
}

function renderSolar(
    parts: SolarDateParts,
    locale: SolarLocale,
    options: Required<Omit<FormatOptions, 'bracket'>> & { bracket: string },
): string {
    const dateParts = options.short
        ? [
              toNumerals(
                  `${String(parts.year).padStart(4, '0')}-${String(parts.month).padStart(2, '0')}-${String(parts.day).padStart(2, '0')}`,
                  options.numerals,
              ).replace(/-/g, '/'),
          ]
        : [
              toNumerals(String(parts.day), options.numerals),
              monthName(parts.month, locale),
              toNumerals(String(parts.year), options.numerals),
          ];

    // Day, month and year read as one run, separated by spaces. A weekday in
    // front of them is a separate thought, so it gets a comma.
    const lead = options.weekday
        ? `${weekdayName(parts.weekday, locale)}، `
        : '';

    return lead + dateParts.join(' ');
}

function renderGregorian(
    parts: SolarDateParts,
    locale: SolarLocale,
    options: Required<Omit<FormatOptions, 'bracket'>> & { bracket: string },
    numerals: Numerals,
): string {
    const [year, month, day] = gregorianParts(parts.gregorian);
    const monthLabel = options.short
        ? GREGORIAN_MONTHS_SHORT[month - 1]
        : GREGORIAN_MONTHS[month - 1];

    const dateText = options.short
        ? `${String(year).padStart(4, '0')}/${String(month).padStart(2, '0')}/${String(day).padStart(2, '0')}`
        : `${day} ${monthLabel} ${year}`;

    const lead = options.weekday
        ? `${GREGORIAN_WEEKDAYS_LONG[parts.weekday]}, `
        : '';

    return toNumerals(`${lead}${dateText}`, numerals);
}

/**
 * English weekday names, for the bracketed Gregorian reference.
 *
 * Matches PHP's date('l'), which is always English whatever the process locale
 * is -- so the reference half of the string reads identically on both sides of
 * the app, and identically to what the PHP engine prints.
 */
const GREGORIAN_WEEKDAYS_LONG: readonly string[] = [
    'Sunday',
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
];

type ResolvedOptions = {
    primary: PrimaryCalendar;
    secondary: SecondaryCalendar;
    numerals: Numerals;
    secondaryNumerals: Numerals;
    weekday: boolean;
    short: boolean;
    bracket: string;
};

/**
 * Fill in every option, so no call site has to.
 *
 * These defaults are the store's real settings, kept in step with
 * config/calendar.php on the server. A caller that needs to differ passes the
 * option; a caller that does not gets the store's decision.
 */
function resolveOptions(options: FormatOptions): ResolvedOptions {
    return {
        primary: options.primary ?? 'solar',
        secondary: options.secondary ?? 'gregorian',
        numerals: options.numerals ?? 'fa',
        secondaryNumerals: options.secondaryNumerals ?? 'latn',
        weekday: options.weekday ?? false,
        short: options.short ?? false,
        bracket: options.bracket ?? '({secondary})',
    };
}

// ---------------------------------------------------------------------------
// Month grids, for a date picker
// ---------------------------------------------------------------------------

/**
 * Build one month as a grid of weeks, ready to draw.
 *
 * Weeks start on Saturday, where the Afghan week does. In an RTL layout that
 * also puts the first column on the right, which is where a Dari or Pashto
 * reader expects Sunday to be -- one ordering for everyone, with no per-locale
 * week start to keep in sync.
 *
 * Every cell carries BOTH calendars. The shopper reads ۱۵ شهریور; the value
 * that goes back to the server is 2025-09-06.
 */
export function monthGrid(
    jalaliYear: number,
    jalaliMonth: number,
    locale?: string | null,
): MonthGrid {
    const resolvedLocale = normaliseLocale(locale);

    const firstIso = toGregorianIso(jalaliYear, jalaliMonth, 1);
    const firstWeekday = weekdayOfGregorian(firstIso);
    const length = daysInMonth(jalaliYear, jalaliMonth);

    // Blank cells in front of the 1st so it lands under its weekday.
    const leading = (firstWeekday - WEEK_STARTS_ON + 7) % 7;
    const cells: GridDay[] = [];

    for (let offset = -leading; offset < length; offset++) {
        const parts = toParts(addDays(firstIso, offset));

        cells.push({
            day: parts.day,
            month: parts.month,
            year: parts.year,
            iso: parts.iso,
            gregorian: parts.gregorian,
            weekday: parts.weekday,
            inMonth: offset >= 0 && offset < length,
        });
    }

    // Pad the end so the last week is a full row. The filler days are real
    // dates from the next month, so hovering one still shows a real day.
    while (cells.length % 7 !== 0) {
        const parts = toParts(addDays(firstIso, cells.length));

        cells.push({
            day: parts.day,
            month: parts.month,
            year: parts.year,
            iso: parts.iso,
            gregorian: parts.gregorian,
            weekday: parts.weekday,
            inMonth: false,
        });
    }

    const weeks: GridDay[][] = [];
    for (let index = 0; index < cells.length; index += 7) {
        weeks.push(cells.slice(index, index + 7));
    }

    return {
        year: jalaliYear,
        month: jalaliMonth,
        isLeapYear: isLeapYear(jalaliYear),
        daysInMonth: length,
        monthName: monthName(jalaliMonth, resolvedLocale),
        weekdays: Array.from({ length: 7 }, (_, offset) =>
            weekdayName((WEEK_STARTS_ON + offset) % 7, resolvedLocale, true),
        ),
        weeks,
    };
}

/** The month a date belongs to, as a grid. What a picker opens on. */
export function monthGridFor(
    date: Date | string | number,
    locale?: string | null,
): MonthGrid {
    const solar = fromGregorian(date);

    return monthGrid(solar.year, solar.month, locale);
}

/**
 * Today in the Solar Hijri calendar.
 *
 * The timezone defaults to Asia/Kabul: a store in Afghanistan. Asking for
 * "today" in the browser's own zone would show the wrong day for a few hours
 * around midnight, and on a date that changes once a year that is exactly the
 * wrong place to be wrong.
 */
export function today(timezone = 'Asia/Kabul'): SolarDateParts {
    const formatter = new Intl.DateTimeFormat('en-CA', {
        timeZone: timezone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    });

    return toParts(formatter.format(new Date()));
}
