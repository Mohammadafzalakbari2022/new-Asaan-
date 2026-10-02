<?php

namespace Cartxis\PhonePe\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PhonePePaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Only ever inserts. The row is PhonePe's default setup, but once it
     * exists it belongs to the shopkeeper, so re-running the seeder must not
     * switch it back on or throw away the credentials they entered.
     */
    public function run(): void
    {
        if (DB::table('payment_methods')->where('code', 'phonepe')->exists()) {
            return;
        }

        DB::table('payment_methods')->insert([
            'code' => 'phonepe',
            'name' => 'PhonePe',
            'description' => 'Pay securely using PhonePe with UPI, credit/debit cards, net banking, and wallets',
            'type' => 'other',
            'is_active' => false,
            'is_default' => false,
            'sort_order' => 7,
            'instructions' => 'You will be redirected to PhonePe to complete your payment securely.',
            'configuration' => json_encode([
                'mode'                => 'production',
                // Live credentials
                'merchant_id'         => '',
                'client_id'           => '',
                'client_secret'       => '',
                'client_version'      => 1,
                // Test (UAT) credentials
                'test_merchant_id'    => '',
                'test_client_id'      => '',
                'test_client_secret'  => '',
                'test_client_version' => 1,
                // Webhook
                'callback_username'   => '',
                'callback_password'   => '',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
