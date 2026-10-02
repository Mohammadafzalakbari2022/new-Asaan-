import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * The two currencies the store trades in, and the shape the API sends.
 *
 * The keys are the column names on the server's `currencies` table:
 * `symbol_position` is 'before' or 'after' the amount, `decimal_places` is how
 * many decimal places are ever shown. AFN has decimal_places: 0 -- there is no
 * practical afghani subunit, so the price is 500 and never 500.00.
 */
export interface Currency {
    code: string;
    name: string;
    symbol: string;
    symbol_position: 'before' | 'after';
    decimal_places: number;
    exchange_rate: number;
    is_default: boolean;
    sort_order: number;
}

/** The base currency. Every stored price is in this one. */
export const BASE_CODE = 'AFN';

/** The one optional display currency. */
export const OPTIONAL_CODE = 'USD';

/**
 * Fallback when the page has not been given currency props -- during an error
 * page render, or a component mounted outside the layout. AFN, so money is
 * never rendered in the wrong symbol just because a prop was missing.
 */
const FALLBACK: Currency = {
    code: BASE_CODE,
    name: 'Afghan Afghani',
    symbol: '؋',
    symbol_position: 'before',
    decimal_places: 0,
    exchange_rate: 1,
    is_default: true,
    sort_order: 1,
};

/** Read an array prop that may be missing or the wrong shape. */
function readList(value: unknown): Currency[] {
    if (!Array.isArray(value)) return [];
    return value.filter((entry): entry is Currency => !!entry && typeof entry === 'object');
}

/**
 * Currency composable -- the single place the storefront turns a stored AFN
 * price into something to put on screen.
 *
 * THE ONE RULE: the number that arrives at formatPrice() is the stored afghani
 * amount and must never be sent back to the server, added to a cart, or
 * compared against a total. Choosing USD changes the label, never the price.
 * Money is only converted at the edges -- for display here, and for a card
 * gateway's settlement currency on the server.
 */
export function useCurrency() {
    const page = usePage();

    /** The two currencies the store supports, base first. */
    const currencies = computed<Currency[]>(() => {
        const list = readList(page.props.currencies);
        if (list.length > 0) return list;
        return [FALLBACK];
    });

    /** What the shopper is looking at right now. Always one of `currencies`. */
    const active = computed<Currency>(() => {
        const shared = page.props.displayCurrency as Currency | null | undefined;
        if (shared && shared.code) {
            const match = currencies.value.find((c) => c.code === shared.code);
            if (match) return match;
        }
        const base = page.props.currency as { code?: string } | null | undefined;
        const fallback = base?.code
            ? currencies.value.find((c) => c.code === base.code)
            : undefined;
        return fallback ?? currencies.value[0] ?? FALLBACK;
    });

    /** True when the shopper chose dollars. */
    const isConverted = computed(() => active.value.code !== BASE_CODE);

    /** The base currency, which is what stored prices are in. */
    const base = computed<Currency>(
        () => currencies.value.find((c) => c.code === BASE_CODE) ?? FALLBACK,
    );

    /** "1 USD = 71 AFN" -- the rate behind every converted figure on screen. */
    const rateLabel = computed(() => `1 ${OPTIONAL_CODE} = ${usdToAfn.value} ${BASE_CODE}`);

    const usdToAfn = computed<number>(() => {
        const usd = currencies.value.find((c) => c.code === OPTIONAL_CODE);
        const rate = usd?.exchange_rate;
        return typeof rate === 'number' && rate > 0 ? rate : 71;
    });

    /**
     * Convert a stored AFN amount into the shopper's currency.
     *
     * PRESENTATION ONLY. The result is for putting on a screen. Never send it
     * back to the server, never store it, never add it to a cart -- a rate
     * change would leave it disagreeing with the afghani figure it came from.
     */
    const convertForDisplay = (afnAmount: number, code?: string): number => {
        const target = code
            ? currencies.value.find((c) => c.code === code)
            : active.value;

        if (!target || target.code === BASE_CODE) return afnAmount;

        const rate = target.exchange_rate;
        if (typeof rate !== 'number' || rate <= 0) return afnAmount;

        return roundTo(afnAmount / rate, target.decimal_places);
    };

    /**
     * Format a stored AFN price for the shopper's currency.
     *
     * Converts when they are viewing dollars, rounds to the display currency's
     * decimal places, and never touches the stored value.
     *
     * @param afnAmount - A price as stored, i.e. in afghani
     * @param options.decimalPlaces - Override the decimal places
     * @param options.showSymbol - Set false for a bare number
     * @param options.showCode - Use "AFN 500" instead of the symbol
     */
    const formatPrice = (
        afnAmount: number,
        options?: { decimalPlaces?: number; showSymbol?: boolean; showCode?: boolean },
    ): string => {
        const currency = active.value;
        const amount = Number.isFinite(afnAmount) ? afnAmount : 0;

        const decimalPlaces = options?.decimalPlaces ?? currency.decimal_places;
        const showSymbol = options?.showSymbol !== false;
        const showCode = options?.showCode === true;

        // Converted figures keep the target currency's decimal places. AFN is
        // pinned to zero so it can never render "500.00" even if the prop drifts.
        const value = roundTo(convertForDisplay(amount), decimalPlaces);

        const formatted = new Intl.NumberFormat('en-US', {
            minimumFractionDigits: decimalPlaces,
            maximumFractionDigits: decimalPlaces,
        }).format(value);

        if (!showSymbol) return formatted;
        if (showCode) return `${currency.code} ${formatted}`;

        return currency.symbol_position === 'after'
            ? `${formatted}${currency.symbol}`
            : `${currency.symbol}${formatted}`;
    };

    /** Format a stored AFN price, labelled with the code rather than a symbol. */
    const formatPriceWithCode = (afnAmount: number): string => formatPrice(afnAmount, { showCode: true });

    /**
     * Format a number in AFN specifically, ignoring the shopper's choice.
     *
     * For the one place a single currency has to be right: a payment gateway's
     * charge, an invoice, or a total the customer is about to be charged.
     */
    const formatAfn = (afnAmount: number, options?: { showSymbol?: boolean }): string => {
        const decimalPlaces = base.value.decimal_places;
        const formatted = new Intl.NumberFormat('en-US', {
            minimumFractionDigits: decimalPlaces,
            maximumFractionDigits: decimalPlaces,
        }).format(roundTo(Number.isFinite(afnAmount) ? afnAmount : 0, decimalPlaces));

        if (options?.showSymbol === false) return formatted;
        return base.value.symbol_position === 'after'
            ? `${formatted}${base.value.symbol}`
            : `${base.value.symbol}${formatted}`;
    };

    const getSymbol = (): string => active.value.symbol;

    const getCode = (): string => active.value.code;

    const getDecimalPlaces = (): number => active.value.decimal_places;

    /**
     * Switch the shopper's currency.
     *
     * The choice is a session cookie, not a per-request value, so the server
     * keeps rendering the same figure on every page. The list passed in is
     * ignored by the server -- it only accepts AFN or USD.
     */
    const setCurrency = (code: string, onDone?: () => void): void => {
        if (!currencies.value.some((c) => c.code === code)) return;
        if (code === active.value.code) {
            onDone?.();
            return;
        }

        fetch('/currency', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Inertia': String(true),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ code }),
        })
            .then(() => {
                window.location.reload();
                onDone?.();
            })
            .catch(() => onDone?.());
    };

    return {
        currency: active,
        currencies,
        active,
        base,
        isConverted,
        usdToAfn,
        rateLabel,
        formatPrice,
        formatPriceWithCode,
        formatAfn,
        convertForDisplay,
        setCurrency,
        getSymbol,
        getCode,
        getDecimalPlaces,
    };
}

/** Round to a fixed number of decimals without the float noise of * 10 ** n. */
function roundTo(value: number, decimals: number): number {
    const places = Math.max(0, Math.min(20, decimals));
    return Number(value.toFixed(places));
}
