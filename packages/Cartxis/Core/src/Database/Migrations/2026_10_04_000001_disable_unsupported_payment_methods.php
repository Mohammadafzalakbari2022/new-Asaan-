<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Switch off the payment methods this store does not use.
 *
 * The store offers Bank Transfer, HesabPay and Cash on Delivery only. The
 * gateway packages (Stripe, PayPal, RazorPay, PayUMoney, PhonePe) are still
 * installed, and each seeds its own payment_methods row on boot, so this
 * migration flips those rows inactive once. Checkout lists active methods
 * only, and the admin list is further limited by PaymentMethod::supported(),
 * so an unsupported method can never be offered again.
 */
return new class extends Migration
{
    /**
     * The only methods this store offers.
     *
     * @var list<string>
     */
    private const KEEP = ['cod', 'bank_transfer', 'hesabpay'];

    public function up(): void
    {
        if (! Schema::hasTable('payment_methods')) {
            return;
        }

        DB::table('payment_methods')
            ->whereNotIn('code', self::KEEP)
            ->update(['is_active' => false, 'is_default' => false]);
    }

    public function down(): void
    {
        // Switching the other gateways back on is a deliberate choice, not a
        // rollback, so there is nothing to restore here.
    }
};
