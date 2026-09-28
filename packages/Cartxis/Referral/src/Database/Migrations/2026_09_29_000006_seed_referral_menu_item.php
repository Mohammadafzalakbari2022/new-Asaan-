<?php

use Cartxis\Core\Models\MenuItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $marketing = MenuItem::where('key', 'marketing')
            ->where('location', 'admin')
            ->first();

        MenuItem::updateOrCreate(
            [
                'key' => 'marketing-referrals',
                'location' => 'admin',
            ],
            [
                'title' => 'Referrals',
                'icon' => 'users-round',
                'route' => 'admin.marketing.referrals.index',
                'url' => null,
                'parent_id' => $marketing?->id,
                'order' => 25,
                'permission' => null,
                'active' => true,
                'extension_code' => 'cartxis_referral',
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('menu_items')
            ->where('key', 'marketing-referrals')
            ->delete();
    }
};
