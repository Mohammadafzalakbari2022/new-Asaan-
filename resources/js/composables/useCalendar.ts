import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import {
    formatDate as formatDateEngine,
    formatGregorian as formatGregorianEngine,
    formatShort as formatShortEngine,
    formatSolar as formatSolarEngine,
    formatTime as formatTimeEngine,
    normaliseLocale,
    type FormatOptions,
    type Numerals,
    type TimeOptions,
} from '@/lib/solar-hijri';

/**
 * The calendar decisions the server owns, mirrored into the browser.
 *
 * Shape of the `calendar` Inertia prop built by CalendarConfig::shared(). The
 * defaults below are the same answers the server falls back to, so a page with
 * no prop -- an error screen, a component mounted outside the app shell --
 * still shows a Solar Hijri date rather than nothing.
 */
export interface CalendarSettings {
    primary: 'solar' | 'gregorian';
    secondary: 'solar' | 'gregorian' | 'none';
    numerals: Numerals;
    secondaryNumerals: Numerals;
    bracket: string;
    weekStartsOn: number;
    minYear: number;
    maxYear: number;
}

const DEFAULTS: CalendarSettings = {
    primary: 'solar',
    secondary: 'gregorian',
    numerals: 'fa',
    secondaryNumerals: 'latn',
    bracket: '({secondary})',
    weekStartsOn: 6,
    minYear: 1399,
    maxYear: 1500,
};

function readSettings(value: unknown): CalendarSettings {
    if (!value || typeof value !== 'object') {
        return DEFAULTS;
    }

    const raw = value as Partial<CalendarSettings>;

    return {
        primary: raw.primary ?? DEFAULTS.primary,
        secondary: raw.secondary ?? DEFAULTS.secondary,
        numerals: raw.numerals ?? DEFAULTS.numerals,
        secondaryNumerals: raw.secondaryNumerals ?? DEFAULTS.secondaryNumerals,
        bracket: raw.bracket ?? DEFAULTS.bracket,
        weekStartsOn:
            typeof raw.weekStartsOn === 'number'
                ? raw.weekStartsOn
                : DEFAULTS.weekStartsOn,
        minYear:
            typeof raw.minYear === 'number' ? raw.minYear : DEFAULTS.minYear,
        maxYear:
            typeof raw.maxYear === 'number' ? raw.maxYear : DEFAULTS.maxYear,
    };
}

type DateInput = Date | string | number;

/**
 * One place the whole frontend turns a stored Gregorian date into the line a
 * person reads: ۱۵ شهریور ۱۴۰۴ (6 September 2025).
 *
 * That pairing is the store's decision, made on the server and passed down in
 * the `calendar` prop, so a table cell, an invoice preview and a picker cannot
 * each decide differently. The reader's chosen language decides the month names
 * and, in English, switches the digits to Latin.
 */
export function useCalendar() {
    const page = usePage();
    const props = page.props as Record<string, unknown>;

    const settings = computed<CalendarSettings>(() => readSettings(props.calendar));

    /** The reader's chosen language, 'fa' if the page has not said. */
    const locale = computed<string>(() => {
        const value = props.locale;
        return typeof value === 'string' && value.trim() !== '' ? value : 'fa';
    });

    /** The set the engine actually has names and digits for. */
    const engineLocale = computed(() => normaliseLocale(locale.value));

    /**
     * Right-to-left unless we know better.
     *
     * The locale table the server shares carries a `direction`; without it,
     * anything that is not English is treated as RTL, which is true of every
     * other language this store offers.
     */
    const direction = computed<'rtl' | 'ltr'>(() => {
        const list = props.locales;

        if (Array.isArray(list)) {
            const match = list.find(
                (entry) =>
                    entry &&
                    typeof entry === 'object' &&
                    (entry as { code?: string }).code === locale.value,
            ) as { direction?: string } | undefined;

            if (match?.direction === 'rtl' || match?.direction === 'ltr') {
                return match.direction;
            }
        }

        return engineLocale.value === 'en' ? 'ltr' : 'rtl';
    });

    /** The store pairing, ready to spread into any engine call. */
    const storeDefaults = computed<FormatOptions>(() => ({
        primary: settings.value.primary,
        secondary: settings.value.secondary,
        numerals: settings.value.numerals,
        secondaryNumerals: settings.value.secondaryNumerals,
        bracket: settings.value.bracket,
    }));

    /** '۱۵ شهریور ۱۴۰۴ (6 September 2025)' -- the full pairing. */
    const formatDate = (value: DateInput, options: FormatOptions = {}): string =>
        formatDateEngine(value, locale.value, { ...storeDefaults.value, ...options });

    /** The Solar Hijri half on its own: '۱۵ شهریور ۱۴۰۴'. */
    const formatSolar = (value: DateInput, options: FormatOptions = {}): string =>
        formatSolarEngine(value, locale.value, {
            numerals: settings.value.numerals,
            ...options,
        });

    /**
     * The Gregorian half on its own, in Latin digits: '6 September 2025'.
     *
     * Latin because the reference is the copyable half -- a bank form, a
     * spreadsheet -- so it is never rendered in Persian digits.
     */
    const formatGregorian = (value: DateInput, options: FormatOptions = {}): string =>
        formatGregorianEngine(value, locale.value, {
            numerals: settings.value.secondaryNumerals,
            ...options,
        });

    /** Numerals only, both calendars: '۱۴۰۴/۰۶/۱۵ (2025/09/06)'. */
    const formatShort = (value: DateInput, options: FormatOptions = {}): string =>
        formatShortEngine(value, locale.value, { ...storeDefaults.value, ...options });

    /**
     * In words, with the weekday on both halves:
     * 'شنبه، ۱۵ شهریور ۱۴۰۴ (Saturday, 6 September 2025)'.
     */
    const formatLong = (value: DateInput, options: FormatOptions = {}): string =>
        formatDateEngine(value, locale.value, {
            ...storeDefaults.value,
            weekday: true,
            ...options,
        });

    /**
     * The clock on a timestamp: '۱۴:۳۰'.
     *
     * One run, shared by both calendar halves, drawn in the same digits as the
     * Solar date so a stamp reads as a single sentence rather than a mixed one.
     */
    const formatTime = (value: DateInput, options: TimeOptions = {}): string =>
        formatTimeEngine(value, {
            numerals: settings.value.numerals,
            ...options,
        });

    return {
        settings,
        locale,
        engineLocale,
        direction,
        formatDate,
        formatSolar,
        formatGregorian,
        formatShort,
        formatLong,
        formatTime,
    };
}
