<?php

use Cartxis\Core\Models\MenuItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Settings for the identity programme, and its place in the admin menu.
 *
 * Seeded from a migration rather than from the service provider, because the
 * provider boots before the schema exists on a first run, so an insert from it
 * would only ever happen on a database that already had the settings table.
 */
return new class extends Migration
{
    /**
     * Mirrors the baked-in defaults in Cartxis\Identity\Services\IdentitySettings,
     * so the feature behaves correctly even if this migration never runs.
     */
    protected const DEFAULTS = [
        'identity.enabled' => '1',
        'identity.require_verification_for_referral' => '1',
        'identity.retention_days' => '30',
        'identity.face_match_enabled' => '0',
    ];

    protected const TYPES = [
        'identity.enabled' => 'boolean',
        'identity.require_verification_for_referral' => 'boolean',
        'identity.retention_days' => 'integer',
        'identity.face_match_enabled' => 'boolean',
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::DEFAULTS as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                [
                    'group' => 'identity',
                    'value' => $value,
                    'type' => self::TYPES[$key],
                    'is_public' => false,
                    'extension_code' => 'cartxis_identity',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $this->seedMenuItem();
    }

    /**
     * Under Customers, next to the customer list.
     *
     * The parent lookup is tolerant of the parent not existing yet, the same way
     * the referral menu migration is: the seeder that creates the top level
     * navigation runs separately from migrations.
     */
    private function seedMenuItem(): void
    {
        $customers = MenuItem::where('key', 'customers')
            ->where('location', 'admin')
            ->first();

        MenuItem::updateOrCreate(
            [
                'key' => 'customers-identity',
                'location' => 'admin',
            ],
            [
                'title' => 'Identity Verification',
                'icon' => 'id-card',
                'route' => 'admin.customers.identity.index',
                'url' => null,
                'parent_id' => $customers?->id,
                'order' => 30,
                'permission' => null,
                'active' => true,
                'extension_code' => 'cartxis_identity',
            ]
        );
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('key', 'like', 'identity.%')
            ->delete();

        DB::table('menu_items')
            ->where('key', 'customers-identity')
            ->delete();
    }
};