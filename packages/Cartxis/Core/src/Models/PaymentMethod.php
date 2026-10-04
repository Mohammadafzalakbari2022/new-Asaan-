<?php

namespace Cartxis\Core\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    /**
     * The payment methods this store offers.
     *
     * Everything else (Stripe, PayPal, RazorPay, PayUMoney, PhonePe) is hidden
     * and switched off here even though its extension may still be installed.
     *
     * @var list<string>
     */
    public const SUPPORTED_CODES = ['cod', 'bank_transfer', 'hesabpay'];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'payment_methods';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'is_active',
        'is_default',
        'sort_order',
        'instructions',
        'configuration',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'configuration' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the configuration value for a specific key.
     */
    public function getConfigValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->configuration, $key, $default);
    }

    /**
     * Set a configuration value.
     */
    public function setConfigValue(string $key, mixed $value): void
    {
        $config = $this->configuration ?? [];
        data_set($config, $key, $value);
        $this->configuration = $config;
    }

    /**
     * Calculate fee for a given amount.
     */
    public function calculateFee(float $amount): float
    {
        $fee = $this->getConfigValue('handling_fee', 0);
        $feeType = $this->getConfigValue('handling_fee_type', 'flat');

        if ($feeType === 'percentage') {
            return ($amount * $fee) / 100;
        }

        return $fee;
    }

    /**
     * Check if method is available for the given amount.
     */
    public function isAvailableForAmount(float $amount): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $minAmount = $this->getConfigValue('min_order_amount', 0);
        $maxAmount = $this->getConfigValue('max_order_amount');

        if ($amount < $minAmount) {
            return false;
        }

        if ($maxAmount !== null && $amount > $maxAmount) {
            return false;
        }

        return true;
    }

    /**
     * Check if payment method is available for a specific country.
     */
    public function isAvailableForCountry(?string $countryCode = null): bool
    {
        // The store only sells inside one country, so the country a shopper is
        // in is always the store country. Reading a saved country list and
        // honouring it literally would mean a list written before the store
        // became Afghanistan-only ("US", "CA", "IN") hides the method from
        // every shopper here and leaves them with no way to pay at all.
        //
        // So the saved list is treated as what it now is: stale history. It is
        // still read, both under the name the admin screen writes
        // ("enabled_countries") and the one the seeder has always written
        // ("allowed_countries"), but it can no longer exclude the store's own
        // country. Turning a method off is the is_active switch's job.
        return true;
    }

    /**
     * Limit a query to the payment methods this store offers.
     */
    public function scopeSupported($query)
    {
        return $query->whereIn('code', self::SUPPORTED_CODES);
    }

    /**
     * Get all active payment methods.
     */
    public static function active()
    {
        return static::where('is_active', true)->orderBy('sort_order');
    }

    /**
     * Get the default payment method.
     */
    public static function getDefault(): ?self
    {
        return static::where('is_default', true)->first();
    }

    /**
     * Set this method as default.
     */
    public function setAsDefault(): bool
    {
        // Remove default from all other methods
        static::where('is_default', true)->update(['is_default' => false]);

        // Set this as default
        return $this->update(['is_default' => true]);
    }
}
