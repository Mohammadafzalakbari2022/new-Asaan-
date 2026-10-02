<?php

namespace Cartxis\Core\Models;

use Cartxis\Core\Support\DisplayCurrency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The store's money.
 *
 * This store trades in exactly two currencies: AFN, the Afghan Afghani, which is
 * the base every price is stored in, and USD, which shoppers may optionally ask
 * to see prices in. Nothing else is sellable.
 *
 * THE RATE
 * --------
 * The exchange_rate column on a non-base row means "1 unit of THIS currency is
 * worth N AFN". So the AFN row is always 1.0, and the USD row holds the
 * usd_to_afn rate the owner types into admin settings, labelled there as
 * "1 USD = ? AFN". Storing it on the USD row rather than in a settings table
 * means it travels with the currency to the API and to the admin form, and
 * there is only ever one number to keep correct.
 *
 * STORAGE
 * -------
 * No amount table has a currency column. Prices, order totals and tax are all
 * stored as plain decimals in AFN and that is not changing. The conversion
 * helpers here are therefore DISPLAY ONLY -- see convert() and
 * DisplayCurrency. Never write a converted number back to the database: the
 * rate moves, and a stored USD figure would then disagree with the AFN figure
 * it was derived from.
 */
class Currency extends Model
{
    use HasFactory;

    /**
     * The base currency. Every stored amount is in this currency.
     */
    public const BASE_CODE = 'AFN';

    /**
     * The one optional display currency, for shoppers who think in dollars.
     */
    public const OPTIONAL_CODE = 'USD';

    /**
     * The whole supported list, in display order. The store is two currencies,
     * not a currency picker with 149 wrong answers in it.
     *
     * @var list<string>
     */
    public const SUPPORTED_CODES = [self::BASE_CODE, self::OPTIONAL_CODE];

    /**
     * Starting rate used when the owner has not set one. Roughly the market
     * rate; the admin edits it.
     */
    public const DEFAULT_USD_TO_AFN = 71.0;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'name',
        'symbol',
        'symbol_position',
        'decimal_places',
        'exchange_rate',
        'is_default',
        'is_active',
        'sort_order',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'decimal_places' => 'integer',
        'exchange_rate' => 'float',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // When setting a currency as default, unset other defaults
        static::saving(function ($currency) {
            if ($currency->is_default) {
                static::where('id', '!=', $currency->id)
                    ->update(['is_default' => false]);
            }
        });

        // Ensure at least one currency is default
        static::deleted(function ($currency) {
            if ($currency->is_default) {
                $newDefault = static::where('is_active', true)
                    ->orderBy('sort_order')
                    ->first();
                
                if ($newDefault) {
                    $newDefault->update(['is_default' => true]);
                }
            }
        });
    }

    /**
     * Scope a query to only include active currencies.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include the default currency.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope a query to the two currencies this store supports.
     *
     * The other ~147 rows from the old country-derived seeder are switched off
     * rather than deleted, so anything that still points at one of them keeps
     * resolving. This scope is what makes that safe to do.
     */
    public function scopeSupported($query)
    {
        return $query->whereIn('code', self::SUPPORTED_CODES);
    }

    /**
     * Scope a query to currencies a shopper is allowed to pick.
     */
    public function scopeSelectable($query)
    {
        return $query->active()->supported();
    }

    /**
     * The currencies a shopper may choose, in display order.
     */
    public static function selectable(): \Illuminate\Support\Collection
    {
        return static::query()
            ->selectable()
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();
    }

    /**
     * Get the default currency.
     *
     * Falls back to the base currency so a store whose currencies table has
     * been half-populated still formats money in AFN rather than in nothing.
     */
    public static function getDefault(): ?self
    {
        return static::where('is_default', true)->first()
            ?? static::getByCode(self::BASE_CODE);
    }

    /**
     * Get currency by code.
     */
    public static function getByCode(string $code): ?self
    {
        return static::where('code', strtoupper(trim($code)))->first();
    }

    /**
     * True when this code is one the store supports.
     */
    public static function isSupportedCode(?string $code): bool
    {
        return $code !== null
            && in_array(strtoupper(trim($code)), self::SUPPORTED_CODES, true);
    }

    /**
     * How many decimals this currency is ever allowed to show.
     *
     * The afghani has no practical subunit -- prices are whole afghani -- so it
     * is pinned to zero here rather than trusting the column. Even if the column
     * drifts back to 2, or a row is edited by hand, the output stays "500" and
     * never "500.00".
     */
    public function displayDecimals(): int
    {
        return $this->isBaseCurrency() ? 0 : max(0, (int) $this->decimal_places);
    }

    /**
     * True when this row is the base (AFN) currency.
     */
    public function isBaseCurrency(): bool
    {
        return $this->code === self::BASE_CODE;
    }

    /**
     * Format an amount with this currency.
     *
     * The number is rounded for READING ONLY. The value passed in and the value
     * stored are untouched, so nothing here can quietly change a stored price.
     *
     * @param  float|null  $amount  The amount in THIS currency, not converted.
     */
    public function formatAmount(?float $amount): string
    {
        $decimals = $this->displayDecimals();

        $formattedAmount = number_format(
            (float) $amount,
            $decimals,
            '.',
            ','
        );

        return $this->symbol_position === 'after'
            ? $formattedAmount . $this->symbol
            : $this->symbol . $formattedAmount;
    }

    /**
     * Format an amount with this currency.
     *
     * Kept because Order::getFormattedTotalAttribute() and
     * OrderItem::getFormattedPriceAttribute() call it.
     */
    public function format(float $amount): string
    {
        return $this->formatAmount($amount);
    }

    /**
     * Convert a canonical AFN amount into this currency.
     *
     * PRESENTATION ONLY. This is not for storage and not for anything a
     * customer is charged: money is charged in the gateway's own settlement
     * currency, which is resolved by GatewayCurrency, and the canonical AFN
     * figure is the one that is authoritative. A converted number belongs on
     * screen, in a PDF, or in a gateway payload -- never in the database.
     *
     * Division, because exchange_rate is "1 unit of this currency = N AFN".
     * A zero or missing rate cannot happen for a supported row (the seeder
     * writes at least DEFAULT_USD_TO_AFN) but is guarded anyway: guessing
     * would silently show a wrong price, so it returns the base amount.
     */
    public function convert(float $canonicalAfnAmount): float
    {
        if ($this->isBaseCurrency()) {
            return $canonicalAfnAmount;
        }

        $rate = (float) $this->exchange_rate;

        return $rate > 0 ? $canonicalAfnAmount / $rate : $canonicalAfnAmount;
    }

    /**
     * The usd_to_afn rate, i.e. the answer to "1 USD = ? AFN".
     *
     * Lives on the USD row's exchange_rate column. Read through here so there is
     * exactly one definition of what that column means.
     */
    public static function usdToAfn(): float
    {
        $usd = static::getByCode(self::OPTIONAL_CODE);

        $rate = $usd ? (float) $usd->exchange_rate : 0.0;

        return $rate > 0 ? $rate : self::DEFAULT_USD_TO_AFN;
    }

    /**
     * The label the admin form shows above the rate field.
     */
    public static function exchangeRateLabel(): string
    {
        return '1 ' . self::OPTIONAL_CODE . ' = ? ' . self::BASE_CODE;
    }

    /**
     * The shape the API and the storefront both consume.
     *
     * The keys are the COLUMN names, deliberately: decimal_places and
     * symbol_position, not "decimals" and "position". There are no columns
     * called decimals or position, so a shorter key name invents a vocabulary
     * the mobile app then has to be taught twice -- once for the API and once
     * for its own cache -- and any consumer reading the real columns gets null.
     *
     * decimal_places is what the column says; for the afghani that is the same
     * zero displayDecimals() forces, so the two can never disagree on screen.
     *
     * @return array<string, mixed>
     */
    public function toDisplayArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'symbol' => $this->symbol,
            'decimal_places' => $this->displayDecimals(),
            'symbol_position' => $this->symbol_position,
            'exchange_rate' => (float) $this->exchange_rate,
            'is_default' => (bool) $this->is_default,
            'sort_order' => (int) $this->sort_order,
        ];
    }

    /**
     * Convert and format a canonical AFN amount for the shopper's chosen
     * currency. The one call a template wants.
     */
    public static function formatForDisplay(float $canonicalAfnAmount, ?string $displayCode = null): string
    {
        return DisplayCurrency::resolve($displayCode)->formatAmount(
            DisplayCurrency::convert($canonicalAfnAmount, $displayCode)
        );
    }
}
