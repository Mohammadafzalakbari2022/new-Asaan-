<?php

declare(strict_types=1);

use Cartxis\Service\Services\ServiceMenuService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add the Services entries to the admin sidebar and the storefront menus.
 *
 * Both menus are database-driven, so these rows are the whole of the navigation
 * work: no theme or layout file has to change for "Services" to appear.
 *
 * The admin rows belong under Catalog, beside Products, Categories and Brands,
 * because a service is something the owner sells. A brand new store has no
 * Catalog row yet when migrations run, so those rows are added without a parent
 * and tucked under Catalog afterwards by the post-seed sync. Nothing is ever
 * lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(ServiceMenuService::class)->sync();
    }

    public function down(): void
    {
        DB::table('menu_items')
            ->whereIn('key', array_column(ServiceMenuService::items(), 'key'))
            ->delete();

        DB::table('menu_items')
            ->where('location', 'storefront')
            ->whereIn('key', array_column(ServiceMenuService::storefrontItems(), 'key'))
            ->delete();
    }
};
