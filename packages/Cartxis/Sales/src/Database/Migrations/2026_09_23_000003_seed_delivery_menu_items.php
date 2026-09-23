<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add the Delivery menu entries to the admin sidebar.
     */
    public function up(): void
    {
        $salesId = DB::table('menu_items')->where('key', 'sales')->value('id');

        if (!$salesId) {
            return;
        }

        $now = now();

        $items = [
            [
                'key' => 'sales-deliveries',
                'title' => 'Deliveries',
                'icon' => 'map-pin',
                'route' => 'admin.sales.deliveries.index',
                'order' => 6,
            ],
            [
                'key' => 'sales-delivery-staff',
                'title' => 'Delivery Staff',
                'icon' => 'users',
                'route' => 'admin.sales.delivery-staff.index',
                'order' => 7,
            ],
        ];

        foreach ($items as $item) {
            DB::table('menu_items')->updateOrInsert(
                ['key' => $item['key']],
                array_merge($item, [
                    'parent_id' => $salesId,
                    'location' => 'admin',
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        DB::table('menu_items')->whereIn('key', ['sales-deliveries', 'sales-delivery-staff'])->delete();
    }
};