/**
 * The Solar Hijri engine, verified against the same table the PHP engine is.
 *
 * ------------------------------------------------------------------------
 * WHY THIS TEST EXISTS
 * ------------------------------------------------------------------------
 * The Solar Hijri calendar is implemented twice in this repository: once in
 * PHP for emails, invoices and PDFs, and once in TypeScript for the admin
 * screens. That is not a design choice so much as a fact of the stack -- mPDF
 * runs no JavaScript, and the browser runs no PHP.
 *
 * Two copies of a calendar drift. This is the thing that stops them.
 *
 * Every case below reads the SAME vector table the PHP suite reads,
 * solar-hijri-vectors.json. That file's Gregorian figures were produced by ICU's
 * persian calendar through Intl.DateTimeFormat -- an implementation sharing no
 * code with either of ours -- and were cross-checked in the opposite direction
 * before being written. So neither copy is marking its own homework: both are
 * being measured against an outside calendar, and against each other.
 *
 * Run it with:  npm run test:calendar
 *
 * No test runner dependency, because the point of the engine is that it has
 * none, and adding vitest to prove a dependency-light module is dependency-free
 * would be a poor trade. Node's built-in runner is enough.
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve as resolvePath } from 'node:path';

import {
    ANCHOR_GREGORIAN,
    LEAP_REMAINDERS,
    MAX_JALALI_YEAR,
    MIN_JALALI_YEAR,
    addDays,
    dayOfYear,
    daysInMonth,
    daysInYear,
    formatDate,
    formatGregorian,
    formatLong,
    formatShort,
    formatSolar,
    formatTime,
    fromGregorian,
    fromNumerals,
    isLeapYear,
    leapYears,
    monthGrid,
    monthLengths,
    monthName,
    monthNames,
    normaliseLocale,
    nowruzGregorian,
    nowruzMarchDay,
    toGregorianIso,
    toNumerals,
    toParts,
    toSolarIso,
    weekdayOfGregorian,
    weekdayOfSolar,
    weekdayName,
    weekdayNames,
} from './solar-hijri.ts';

const here = dirname(fileURLToPath(import.meta.url));

const VECTORS_PATH = resolvePath(
    here,
    '../../../packages/Cartxis/Calendar/src/Support/solar-hijri-vectors.json',
);

interface SolarVector {
    solar: string;
    gregorian: string;
    leapYear: boolean;
    daysInMonth: number;
    weekday: number;
    note: string;
}

interface GregorianVector {
    gregorian: string;
    solar: string;
    leapYear: boolean;
    weekday: number;
    note: string;
}

interface NowruzVector {
    year: number;
    gregorian: string;
    leapYear: boolean;
}

interface VectorTable {
    supportedRange: { minJalaliYear: number; maxJalaliYear: number };
    leapRemainders: number[];
    anchors: { solar: string; gregorian: string; note: string }[];
    solarToGregorian: SolarVector[];
    gregorianToSolar: GregorianVector[];
    nowruzByYear: NowruzVector[];
}

const vectors: VectorTable = JSON.parse(readFileSync(VECTORS_PATH, 'utf8'));

const split = (iso: string): [number, number, number] => {
    const [year, month, day] = iso.split('-').map(Number);

    return [year, month, day];
};

// ---------------------------------------------------------------------------
// The vector tables
// ---------------------------------------------------------------------------

test('the vector table itself is well formed', () => {
    assert.ok(
        vectors.solarToGregorian.length >= 20,
        `expected at least 20 forward vectors, found ${vectors.solarToGregorian.length}`,
    );
    assert.ok(
        vectors.gregorianToSolar.length >= 10,
        `expected at least 10 reverse vectors, found ${vectors.gregorianToSolar.length}`,
    );
    assert.ok(
        vectors.nowruzByYear.length > 50,
        `expected the Nowruz table to span the range, found ${vectors.nowruzByYear.length} rows`,
    );

    assert.deepEqual(vectors.leapRemainders, LEAP_REMAINDERS);
});

test('every Solar Hijri vector converts to its Gregorian date', () => {
    for (const vector of vectors.solarToGregorian) {
        const [year, month, day] = split(vector.solar);

        assert.equal(
            toGregorianIso(year, month, day),
            vector.gregorian,
            `${vector.solar} should be ${vector.gregorian} (${vector.note})`,
        );
    }
});

test('every Gregorian vector converts back to its Solar Hijri date', () => {
    for (const vector of vectors.gregorianToSolar) {
        const [year, month, day] = split(vector.solar);

        assert.equal(
            toSolarIso(fromGregorian(vector.gregorian)),
            vector.solar,
            `${vector.gregorian} should be ${vector.solar} (${vector.note})`,
        );

        assert.equal(toGregorianIso(year, month, day), vector.gregorian);
    }
});

test('every vector agrees about leap years, month lengths and weekdays', () => {
    for (const vector of [...vectors.solarToGregorian, ...vectors.gregorianToSolar]) {
        const [year, month, day] = split(vector.solar);

        assert.equal(
            isLeapYear(year),
            vector.leapYear,
            `${vector.solar}: leap-year flag disagrees (${vector.note})`,
        );
        assert.equal(
            weekdayOfSolar(year, month, day),
            vector.weekday,
            `${vector.solar}: weekday disagrees with the Gregorian date ${vector.gregorian}`,
        );
    }

    for (const vector of vectors.solarToGregorian) {
        const [year, month] = split(vector.solar);

        assert.equal(
            daysInMonth(year, month),
            vector.daysInMonth,
            `${vector.solar}: month length disagrees (${vector.note})`,
        );
    }
});

test('Solar New Year lands on the date the table gives for every year', () => {
    for (const vector of vectors.nowruzByYear) {
        assert.equal(
            nowruzGregorian(vector.year),
            vector.gregorian,
            `Nowruz ${vector.year} should be ${vector.gregorian}`,
        );
        assert.equal(isLeapYear(vector.year), vector.leapYear);
    }
});

test('the anchor is 1 Farvardin 1400 == 21 March 2021', () => {
    assert.equal(vectors.anchors[0].solar, '1400-01-01');
    assert.equal(vectors.anchors[0].gregorian, ANCHOR_GREGORIAN);
    assert.equal(nowruzGregorian(1400), '2021-03-21');
    assert.equal(toSolarIso(fromGregorian('2021-03-21')), '1400-01-01');
});

// ---------------------------------------------------------------------------
// Nowruz
// ---------------------------------------------------------------------------

test('Nowruz 1404 is 21 March 2025', () => {
    // The sanity check named in the brief. 6 September 2025 is 15 Shahrivar
    // 1404, which is the 207th day of the year, and the year began 198 days
    // earlier on 21 March.
    assert.equal(nowruzGregorian(1404), '2025-03-21');
    assert.equal(nowruzMarchDay(1404), 21);
    assert.equal(toSolarIso(fromGregorian('2025-03-21')), '1404-01-01');
    assert.equal(toSolarIso(fromGregorian('2025-09-06')), '1404-06-15');
    assert.equal(toGregorianIso(1404, 6, 15), '2025-09-06');
});

test('a leap year starts on 20 March, and the day before is 30 Esfand', () => {
    // 1403 is a leap year: 1403 mod 33 is 17, which is in the set.
    assert.equal(1403 % 33, 17);
    assert.equal(isLeapYear(1403), true);
    assert.equal(nowruzGregorian(1403), '2024-03-20');
    assert.equal(nowruzMarchDay(1403), 20);

    // The leap day is the last day of Esfand, and it is the day before Nowruz.
    assert.equal(daysInMonth(1403, 12), 30);
    assert.equal(toGregorianIso(1403, 12, 30), '2025-03-20');
    assert.equal(toSolarIso(fromGregorian('2025-03-20')), '1403-12-30');
    assert.equal(toSolarIso(fromGregorian('2025-03-19')), '1403-12-29');
});

test('20 March can also be Nowruz in a common year, which is the trap', () => {
    // 1407 is NOT a leap year -- 1407 mod 33 is 21 -- yet Nowruz 1407 still
    // falls on 20 March, because 1408 is the leap year and Nowruz drifts a day
    // early while it approaches. So "20 March" does not mean "leap year", and
    // code that assumes it does will be wrong here.
    assert.equal(1407 % 33, 21);
    assert.equal(isLeapYear(1407), false);
    assert.equal(nowruzGregorian(1407), '2028-03-20');
    assert.equal(daysInMonth(1407, 12), 29);
    assert.equal(toSolarIso(fromGregorian('2028-03-19')), '1406-12-29');
    assert.equal(toSolarIso(fromGregorian('2028-03-20')), '1407-01-01');
});

test('Nowruz never leaves 20 or 21 March across the supported range', () => {
    const { minJalaliYear, maxJalaliYear } = vectors.supportedRange;

    for (let year = minJalaliYear; year <= maxJalaliYear; year++) {
        const gregorian = nowruzGregorian(year);
        const marchDay = Number(gregorian.slice(-2));

        assert.ok(
            marchDay === 20 || marchDay === 21,
            `Nowruz ${year} fell on ${marchDay} March, outside 20-21 (${gregorian})`,
        );
        assert.equal(gregorian.slice(5, 7), '03');
    }
});

// ---------------------------------------------------------------------------
// The leap-year rule
// ---------------------------------------------------------------------------

test('a year is a leap year exactly when year mod 33 is in the remainder set', () => {
    for (let year = MIN_JALALI_YEAR; year <= MAX_JALALI_YEAR; year++) {
        const remainder = ((year % 33) + 33) % 33;
        const expected = LEAP_REMAINDERS.includes(remainder);

        assert.equal(
            isLeapYear(year),
            expected,
            `year ${year} (remainder ${remainder}) should ${expected ? '' : 'not '}be a leap year`,
        );
    }
});

test('there are eight leap years in every 33-year cycle', () => {
    for (let start = 1300; start <= 1500; start += 33) {
        assert.equal(
            leapYears(start, start + 32).length,
            8,
            `${start}..${start + 32} should hold eight leap years`,
        );
    }
});

test('the 33-year cycle averages out at the length of a solar year', () => {
    // 33 solar years is 12025.75 days; 33 calendar years hold 365 or 366.
    // Getting within a couple of days of the tropical year over 100 years is
    // the whole point of the irregular cycle.
    let days = 0;
    for (let year = 1400; year < 1500; year++) {
        days += daysInYear(year);
    }

    assert.equal(days, 36524);
    assert.ok(Math.abs(days / 100 - 365.2425) < 0.01);
});

test('the known leap years in and around the present are leap years', () => {
    for (const year of [1399, 1403, 1408, 1412, 1416, 1420, 1424, 1428, 1432]) {
        assert.equal(isLeapYear(year), true, `${year} should be a leap year`);
    }

    for (const year of [1400, 1401, 1402, 1404, 1405, 1406, 1407, 1409]) {
        assert.equal(isLeapYear(year), false, `${year} should not be a leap year`);
    }
});

// ---------------------------------------------------------------------------
// Month lengths
// ---------------------------------------------------------------------------

test('months 1-6 have 31 days, 7-11 have 30, and Esfand has 29 or 30', () => {
    for (const year of [1400, 1403, 1404, 1444, 1499]) {
        const lengths = monthLengths(year);

        assert.equal(lengths.length, 12);
        assert.deepEqual(lengths.slice(0, 6), [31, 31, 31, 31, 31, 31]);
        assert.deepEqual(lengths.slice(6, 11), [30, 30, 30, 30, 30]);
        assert.equal(lengths[11], isLeapYear(year) ? 30 : 29);

        // The month lengths must add up to the year length, or a date in the
        // last days of Esfand would fall off the end of the year.
        const total = lengths.reduce((sum, length) => sum + length, 0);
        assert.equal(total, daysInYear(year), `month lengths for ${year} must sum to the year length`);
    }
});

test('30 Esfand exists only in a leap year', () => {
    assert.equal(daysInMonth(1403, 12), 30);
    assert.equal(daysInMonth(1408, 12), 30);
    assert.equal(daysInMonth(1404, 12), 29);
    assert.equal(daysInMonth(1407, 12), 29);

    // And 30 Esfand is always the day before the next Nowruz.
    for (const year of leapYears(1400, 1500)) {
        const dayAfter = addDays(toGregorianIso(year, 12, 30), 1);

        assert.equal(
            toGregorianIso(dayAfter.year, dayAfter.month, dayAfter.day),
            nowruzGregorian(year + 1),
            `30 Esfand ${year} must be the day before Nowruz ${year + 1}`,
        );
    }
});

test('a month of 31 days really is 31 days apart', () => {
    // Month lengths are the easiest thing in a calendar to get subtly wrong, so
    // check the arithmetic rather than the table.
    const first = toGregorianIso(1404, 6, 1);
    const last = toGregorianIso(1404, 6, 31);

    assert.equal((Date.parse(last) - Date.parse(first)) / 86400000, 30);

    const sixthMonth = monthGrid(1404, 6, 'en');
    assert.equal(sixthMonth.daysInMonth, 31);
    assert.equal(
        sixthMonth.weeks.flat().filter((cell) => cell.inMonth).at(-1)?.gregorian,
        last,
    );
});

// ---------------------------------------------------------------------------
// Conversion, both directions, exhaustively
// ---------------------------------------------------------------------------

test('every day of the supported range converts both ways with no drift', () => {
    const start = Date.UTC(2020, 2, 20); // 1 Farvardin 1399
    const end = Date.UTC(2121, 2, 20); // 29 Esfand 1500

    let days = 0;
    let previousSerial = Number.NEGATIVE_INFINITY;

    for (let stamp = start; stamp <= end; stamp += 86400000) {
        const gregorian = new Date(stamp).toISOString().slice(0, 10);
        const solar = fromGregorian(gregorian);
        const roundTripped = toGregorianIso(solar.year, solar.month, solar.day);

        assert.equal(
            roundTripped,
            gregorian,
            `${gregorian} round-tripped through ${solar.year}-${solar.month}-${solar.day} as ${roundTripped}`,
        );

        // Solar dates must strictly increase: the day number rises within a
        // year, and the year itself rises at Nowruz.
        const serial =
            solar.year * 1000 + dayOfYear(solar.year, solar.month, solar.day);
        if (previousSerial !== Number.NEGATIVE_INFINITY) {
            assert.ok(serial > previousSerial, `Solar Hijri dates must strictly increase at ${gregorian}`);
        }
        previousSerial = serial;

        days++;
    }

    // 20 March 2020 through 20 March 2121 inclusive: 101 years, 24 of them
    // leap, plus the closing day.
    assert.equal(days, 36890);
});

test('Solar Hijri days and Gregorian days advance in lockstep', () => {
    // Guards the walk that finds a month's first weekday: if the 1st of a month
    // ever landed on the wrong day, a date picker would be off by a column.
    for (let offset = 0; offset < 400; offset++) {
        const gregorian = new Date(
            Date.UTC(2025, 0, 1) + offset * 86400000,
        )
            .toISOString()
            .slice(0, 10);

        const parts = toParts(gregorian);

        assert.equal(
            parts.weekday,
            weekdayOfGregorian(gregorian),
            `weekday disagrees at ${gregorian}`,
        );
        assert.equal(
            weekdayOfSolar(parts.year, parts.month, parts.day),
            parts.weekday,
            `weekday disagrees at ${parts.iso}`,
        );
    }
});

test('a Gregorian leap day is an ordinary day here', () => {
    // 29 February exists in the Gregorian calendar and has no meaning at all
    // in this one. It must still land on a real Solar Hijri date.
    const solar = fromGregorian('2024-02-29');

    assert.equal(solar.month, 12);
    assert.equal(solar.day, 10);
    assert.equal(solar.year, 1402);
    assert.equal(toGregorianIso(solar.year, solar.month, solar.day), '2024-02-29');
});

// ---------------------------------------------------------------------------
// Weekdays
// ---------------------------------------------------------------------------

test('weekdays run Sunday-first, matching date("w") and Date.getDay()', () => {
    assert.equal(weekdayOfGregorian('2021-03-21'), 0); // a Sunday
    assert.equal(weekdayOfGregorian('2021-03-22'), 1);
    assert.equal(weekdayOfGregorian('2025-09-06'), 6); // a Saturday

    assert.equal(weekdayName(0, 'en'), 'Sunday');
    assert.equal(weekdayName(6, 'en'), 'Saturday');
    assert.equal(weekdayNames('fa').length, 7);
    assert.equal(weekdayNames('ps').length, 7);
    assert.equal(weekdayNames('fa')[0], 'یکشنبه');
});

// ---------------------------------------------------------------------------
// Names and locales
// ---------------------------------------------------------------------------

test('every locale has twelve month names and seven weekday names', () => {
    for (const locale of ['en', 'fa', 'fa_alt', 'ps']) {
        assert.equal(monthNames(locale).length, 12, `${locale} needs 12 months`);
        assert.equal(weekdayNames(locale).length, 7, `${locale} needs 7 weekdays`);
        assert.equal(weekdayNames(locale, true).length, 7, `${locale} needs 7 short weekdays`);
    }
});

test('the Dari month names are the ones an Afghan reader expects', () => {
    assert.deepEqual(monthNames('fa'), [
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
    ]);

    // The Afghan names, not the Iranian set. Farvardin and Shahrivar are what
    // PHP's and JavaScript's Intl hand back, and they are wrong for this store.
    assert.equal(monthName(1, 'fa'), 'حمل');
    assert.equal(monthName(6, 'fa'), 'سنبله');
    assert.equal(monthName(6, 'fa_alt'), 'سنبله');
    assert.equal(monthName(1, 'en'), 'Hamal');
    assert.equal(monthName(6, 'en'), 'Sunbula');
});

test('locales resolve, including the regional spellings', () => {
    assert.equal(normaliseLocale('fa'), 'fa');
    assert.equal(normaliseLocale('fa-AF'), 'fa');
    assert.equal(normaliseLocale('fa_AF'), 'fa');
    assert.equal(normaliseLocale('Dari'), 'fa');
    assert.equal(normaliseLocale('prs'), 'fa');
    assert.equal(normaliseLocale('fa_alt'), 'fa_alt');
    assert.equal(normaliseLocale('ps'), 'ps');
    assert.equal(normaliseLocale('ps-AF'), 'ps');
    assert.equal(normaliseLocale('en'), 'en');
    assert.equal(normaliseLocale('en-GB'), 'en');

    // An unknown or missing locale falls back to English rather than printing
    // nothing, or worse, printing a number where a month name belongs.
    assert.equal(normaliseLocale('de'), 'en');
    assert.equal(normaliseLocale(''), 'en');
    assert.equal(normaliseLocale(null), 'en');
    assert.equal(normaliseLocale(undefined), 'en');
});

// ---------------------------------------------------------------------------
// Numerals
// ---------------------------------------------------------------------------

test('numerals convert and convert back', () => {
    assert.equal(toNumerals('1404', 'fa'), '۱۴۰۴');
    assert.equal(toNumerals('1404', 'arab'), '١٤٠٤');
    assert.equal(toNumerals('1404', 'latn'), '1404');

    // Only digits move -- the month name between them is untouched.
    assert.equal(toNumerals('15 Shahrivar 1404', 'fa'), '۱۵ Shahrivar ۱۴۰۴');

    assert.equal(fromNumerals('۱۵ سنبله ۱۴۰۴'), '15 سنبله 1404');
    assert.equal(fromNumerals('١٥ سنبله ١٤٠٤'), '15 سنبله 1404');
    assert.equal(fromNumerals('already latin 15'), 'already latin 15');
});

// ---------------------------------------------------------------------------
// Formatting
// ---------------------------------------------------------------------------

test('the store pairing is Solar Hijri first, Gregorian in brackets', () => {
    assert.equal(
        formatDate('2025-09-06', 'fa'),
        '۱۵ سنبله ۱۴۰۴ (6 September 2025)',
    );

    // Exactly the worked example in the brief, with the alternative Dari name.
    assert.equal(
        formatDate('2025-09-06', 'fa_alt'),
        '۱۵ سنبله ۱۴۰۴ (6 September 2025)',
    );
});

test('the two halves can be asked for on their own', () => {
    assert.equal(formatSolar('2025-09-06', 'fa'), '۱۵ سنبله ۱۴۰۴');
    assert.equal(formatGregorian('2025-09-06', 'en'), '6 September 2025');

    // gregorian() must not print the same date twice.
    assert.ok(!formatGregorian('2025-09-06', 'en').includes('September 2025 ('));

    assert.equal(formatShort('2025-09-06', 'fa'), '۱۴۰۴/۰۶/۱۵ (2025/09/06)');
    assert.equal(
        formatLong('2025-09-06', 'fa'),
        'شنبه، ۱۵ سنبله ۱۴۰۴ (Saturday, 6 September 2025)',
    );
});

test('formatting in English keeps Latin digits for the Gregorian reference', () => {
    assert.equal(
        formatDate('2025-09-06', 'en', { numerals: 'latn' }),
        '15 Sunbula 1404 (6 September 2025)',
    );

    // The secondary half is Latin by default so it can be copied into a bank
    // form without retyping it.
    assert.ok(formatDate('2025-09-06', 'fa').includes('(6 September 2025)'));
});

test('the weekday is joined to the date with a comma, not a space', () => {
    // Otherwise it reads as four loose words rather than "Saturday, the 15th".
    const formatted = formatSolar('2025-09-06', 'fa', { weekday: true });
    assert.equal(formatted, 'شنبه، ۱۵ سنبله ۱۴۰۴');
});

test('the bracket wrapper is configurable', () => {
    assert.equal(
        formatDate('2025-09-06', 'fa', { bracket: '[{secondary}]' }),
        '۱۵ سنبله ۱۴۰۴ [6 September 2025]',
    );

    assert.equal(
        formatDate('2025-09-06', 'fa', { secondary: 'none' }),
        '۱۵ سنبله ۱۴۰۴',
    );
});

test('a date is formatted identically from every kind of input', () => {
    const expected = '۱۵ سنبله ۱۴۰۴ (6 September 2025)';

    assert.equal(formatDate('2025-09-06', 'fa'), expected);
    assert.equal(formatDate('2025-09-06T13:45:00Z', 'fa'), expected);
    assert.equal(formatDate('2025-09-06T13:45:00+04:30', 'fa'), expected);
    assert.equal(formatDate(new Date('2025-09-06T23:59:59Z'), 'fa'), expected);
    assert.equal(formatDate(Date.UTC(2025, 8, 6), 'fa'), expected);
});

test('a timestamp can show its clock time, in the reader\'s digits', () => {
    // One run of time, shared by both halves of the pairing, not two.
    assert.equal(formatTime('2025-09-06T14:30:00'), '۱۴:۳۰');
    assert.equal(formatTime('2025-09-06T14:30:00', { numerals: 'latn' }), '14:30');
    assert.equal(
        formatTime('2025-09-06T14:30:00', { numerals: 'arab' }),
        '١٤:٣٠',
    );

    // Seconds only when asked for.
    assert.equal(
        formatTime('2025-09-06T14:30:09', { seconds: true, numerals: 'latn' }),
        '14:30:09',
    );

    // Midnight and a single-digit hour are zero-padded so a column lines up.
    assert.equal(formatTime('2025-09-06T00:05:00', { numerals: 'latn' }), '00:05');
    assert.equal(formatTime('2025-09-06T09:00:00', { numerals: 'latn' }), '09:00');

    // A stored date with no time is read as the start of the day, not an error.
    assert.equal(formatTime('2025-09-06', { numerals: 'latn' }), '00:00');
});

test('an unreadable timestamp yields an empty clock, never "NaN"', () => {
    // The caller drops an empty run, so bad data leaves a clean date rather than
    // a broken-looking "NaN:NaN" beside it.
    assert.equal(formatTime('not a date'), '');
    assert.equal(formatTime(''), '');
});

// ---------------------------------------------------------------------------
// Month grids
// ---------------------------------------------------------------------------

test('a month grid has full weeks and marks its outside days', () => {
    for (const [year, month] of [
        [1404, 6],
        [1403, 12],
        [1404, 12],
        [1407, 1],
    ] as [number, number][]) {
        const grid = monthGrid(year, month, 'fa');

        assert.equal(grid.daysInMonth, daysInMonth(year, month));
        assert.equal(grid.weekdays.length, 7);
        assert.equal(grid.weeks.length % 1, 0);

        for (const week of grid.weeks) {
            assert.equal(week.length, 7, `week in ${year}-${month} must have 7 days`);
        }

        const inMonth = grid.weeks.flat().filter((cell) => cell.inMonth);
        assert.equal(inMonth.length, grid.daysInMonth);

        // The in-month days must be 1..daysInMonth in order, and each must
        // carry the Gregorian date it stands for.
        inMonth.forEach((cell, index) => {
            assert.equal(cell.day, index + 1);
            assert.equal(cell.gregorian, toGregorianIso(year, month, index + 1));
            assert.equal(cell.iso, toSolarIso({ year, month, day: index + 1 }));
        });

        // The 1st must sit under its own weekday, which is what stops a picker
        // being out by a column.
        const first = inMonth[0];
        assert.equal(first.weekday, weekdayOfGregorian(first.gregorian));
    }
});

test('a grid week starts on Saturday', () => {
    const grid = monthGrid(1404, 6, 'fa');

    // Whatever day the 1st falls on, the first cell of every row is the same
    // weekday, and it is Saturday.
    for (const week of grid.weeks) {
        assert.equal(week[0].weekday, 6);
    }

    assert.deepEqual(grid.weekdays, ['شنبه', 'یک', 'دو', 'سه', 'چهار', 'پنج', 'جمعه']);
});

test('Esfand 1403 has a 30th in the grid, and Esfand 1404 does not', () => {
    const leapGrid = monthGrid(1403, 12, 'fa');
    const commonGrid = monthGrid(1404, 12, 'fa');

    const leapDays = leapGrid.weeks
        .flat()
        .filter((cell) => cell.inMonth)
        .map((cell) => cell.day);
    const commonDays = commonGrid.weeks
        .flat()
        .filter((cell) => cell.inMonth)
        .map((cell) => cell.day);

    assert.equal(leapDays.length, 30);
    assert.equal(commonDays.length, 29);
    assert.ok(leapDays.includes(30));
    assert.ok(!commonDays.includes(30));

    // And that 30th is the day before Nowruz 1404.
    const thirtieth = leapGrid.weeks
        .flat()
        .find((cell) => cell.inMonth && cell.day === 30);
    assert.equal(thirtieth?.gregorian, '2025-03-20');
});

test('a grid can be opened on the month a date falls in', () => {
    const grid = monthGrid(1404, 6, 'fa');

    assert.equal(grid.year, 1404);
    assert.equal(grid.month, 6);
    assert.equal(grid.monthName, 'سنبله');
    assert.equal(grid.isLeapYear, false);
});
