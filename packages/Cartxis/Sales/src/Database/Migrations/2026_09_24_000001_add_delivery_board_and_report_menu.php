<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add the Delivery Board (under Sales) and the Delivery Report (under Reports)
     * menu items, and give the delivery siblings stable ordering.
     */
    public function up(): void
    {
        $now = now();

        $salesId = DB::table('menu_items')->where('key', 'sales')->value('id');
        $reportsId = DB::table('menu_items')->where('key', 'reports')->value('id')
            ?? DB::table('menu_items')->where('title', 'Reports')->whereNull('parent_id')->value('id');

        $deliverySiblings = [
            'sales-delivery-board' => [
                'title' => 'Delivery Board',
                'icon' => 'radar',
                'route' => 'admin.sales.deliveries.board',
                'parent_id' => $salesId,
                'order' => 6,
            ],
            'sales-deliveries' => [
                'title' => 'Deliveries',
                'icon' => 'map-pin',
                'route' => 'admin.sales.deliveries.index',
                'parent_id' => $salesId,
                'order' => 7,
            ],
            'sales-delivery-staff' => [
                'title' => 'Delivery Staff',
                'icon' => 'users',
                'route' => 'admin.sales.delivery-staff.index',
                'parent_id' => $salesId,
                'order' => 8,
            ],
        ];

        foreach ($deliverySiblings as $key => $item) {
            DB::table('menu_items')->updateOrInsert(
                ['key' => $key],
                array_merge($item, [
                    'location' => 'admin',
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }

        if ($reportsId) {
            DB::table('menu_items')->updateOrInsert(
                ['key' => 'reports-delivery'],
                [
                    'title' => 'Delivery Report',
                    'icon' => 'truck',
                    'route' => 'admin.reports.delivery',
                    'parent_id' => $reportsId,
                    'order' => 4,
                    'location' => 'admin',
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        DB::table('menu_items')->whereIn('key', ['sales-delivery-board', 'reports-delivery'])->delete();
    }
};