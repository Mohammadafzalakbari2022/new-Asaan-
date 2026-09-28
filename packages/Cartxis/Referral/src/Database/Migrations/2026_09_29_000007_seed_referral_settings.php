<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Default settings for the referral programme.
     *
     * These match the baked-in defaults in Cartxis\Referral\Services\ReferralSettings,
     * so the app behaves correctly even if this migration never runs.
     */
    protected const DEFAULTS = [
        'referral.enabled' => '1',
        'referral.reward_amount' => '10',
        'referral.threshold_amount' => '500',
        'referral.lock_days' => '180',
        'referral.level_shares' => '[100]',
        'referral.reward_mode' => 'once_per_person',
        'referral.allow_admin_credit' => '1',
        'referral.block_account_deletion' => '1',
        'referral.credit_max_percent_of_order' => '100',
    ];

    protected const TYPES = [
        'referral.enabled' => 'boolean',
        'referral.reward_amount' => 'float',
        'referral.threshold_amount' => 'float',
        'referral.lock_days' => 'integer',
        'referral.level_shares' => 'json',
        'referral.reward_mode' => 'string',
        'referral.allow_admin_credit' => 'boolean',
        'referral.block_account_deletion' => 'boolean',
        'referral.credit_max_percent_of_order' => 'integer',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        foreach (self::DEFAULTS as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                [
                    'group' => 'referral',
                    'value' => $value,
                    'type' => self::TYPES[$key],
                    'is_public' => false,
                    'extension_code' => 'cartxis_referral',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')
            ->where('key', 'like', 'referral.%')
            ->delete();
    }
};
