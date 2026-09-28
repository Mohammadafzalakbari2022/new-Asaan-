<?php

declare(strict_types=1);

namespace Cartxis\Service\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps the Services entries in every menu the owner can click.
 *
 * Menus are database-driven. On a brand new store the menu rows are created by
 * a seeder that runs *after* migrations, so a migration alone cannot know which
 * "Catalog" row to sit under. This service is therefore run twice:
 *
 *  - once from the package migration, which inserts the rows straight away so
 *    they are never missing (top level if Catalog does not exist yet);
 *  - once after seeding, which puts them in their proper place.
 *
 * Both steps are safe to repeat.
 *
 * Two audiences are covered. The admin sidebar gets the management entries
 * (Services, Service Categories). The storefront gets a "Services" link in the
 * header, the mobile menu and the footer, so customers can reach the catalogue
 * and the seeded footer links get real content instead of placeholder text.
 *
 * `menu_items.key` is globally unique, so every key is namespaced per surface
 * (`services`, `services-header`, `services-mobile`, `services-footer*`) to
 * keep one surface's edit from clobbering another's.
 */
class ServiceMenuService
{
    /**
     * The sidebar entries, in the order the owner should see them.
     */
    public static function items(): array
    {
        return [
            [
                'key' => 'services',
                'title' => 'Services',
                'icon' => 'wrench',
                'route' => 'admin.services.index',
                'order' => 8,
            ],
            [
                'key' => 'services-categories',
                'title' => 'Service Categories',
                'icon' => 'folder-tree',
                'route' => 'admin.services.categories.index',
                'order' => 9,
            ],
            [
                'key' => 'services-bookings',
                'title' => 'Service Bookings',
                'icon' => 'clipboard-list',
                'route' => 'admin.services.bookings.index',
                'order' => 10,
            ],
            [
                'key' => 'services-settings',
                'title' => 'Service Settings',
                'icon' => 'settings-2',
                'route' => 'admin.services.settings.edit',
                'order' => 11,
            ],
        ];
    }

    /**
     * The storefront entries.
     *
     * The theme header, footer and mobile drawer all read the same
     * database-driven menu, so adding these rows is what puts "Services" in front
     * of customers. They are owned by this package rather than added to the CMS
     * seeder, so the CMS seeder stays untouched.
     *
     * The keys are prefixed because menu_items.key is unique across the whole
     * table, and the admin sidebar already owns the plain "services" key. The
     * title the customer sees is the same either way.
     */
    public static function storefrontItems(): array
    {
        return [
            [
                'key' => 'services-header',
                'title' => 'Services',
                'icon' => 'wrench',
                'url' => '/services',
                'menu_type' => 'header',
                'parent_key' => null,
                'order' => 2,
            ],
            [
                'key' => 'services-mobile',
                'title' => 'Services',
                'icon' => 'wrench',
                'url' => '/services',
                'menu_type' => 'mobile',
                'parent_key' => null,
                'order' => 3,
            ],
            [
                'key' => 'services-footer',
                'title' => 'Our Services',
                'icon' => null,
                'url' => '#',
                'menu_type' => 'footer',
                'parent_key' => null,
                'order' => 2,
            ],
            [
                'key' => 'services-footer-book',
                'title' => 'Book a Service',
                'icon' => null,
                'url' => '/services',
                'menu_type' => 'footer',
                'parent_key' => 'services-footer',
                'order' => 1,
            ],
            [
                'key' => 'services-footer-track',
                'title' => 'Track a Service',
                'icon' => null,
                'url' => '/services/track',
                'menu_type' => 'footer',
                'parent_key' => 'services-footer',
                'order' => 2,
            ],
        ];
    }

    public function sync(): void
    {
        if (! Schema::hasTable('menu_items')) {
            return;
        }

        $this->syncAdmin();
        $this->syncStorefront();
    }

    protected function syncAdmin(): void
    {
        $catalogId = DB::table('menu_items')
            ->where('key', 'catalog')
            ->where('location', 'admin')
            ->value('id');
        $now = now();

        foreach (self::items() as $item) {
            /*
             * Matched on key *and* location, because the same key is used by the
             * storefront menu. Matching on the key alone would overwrite the
             * customer-facing Services link with the admin row.
             */
            DB::table('menu_items')->updateOrInsert(
                ['key' => $item['key'], 'location' => 'admin'],
                array_merge($item, [
                    'parent_id' => $catalogId ?: null,
                    'url' => null,
                    'permission' => null,
                    'location' => 'admin',
                    'extension_code' => 'service',
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }
    }

    /**
     * The storefront menus are keyed on (key, location) rather than key alone,
     * because the same key may legitimately appear in more than one menu.
     */
    protected function syncStorefront(): void
    {
        $now = now();
        $ids = [];

        foreach (self::storefrontItems() as $item) {
            $parentId = $item['parent_key'] !== null ? ($ids[$item['parent_key']] ?? null) : null;

            $row = [
                'title' => $item['title'],
                'icon' => $item['icon'],
                'route' => null,
                'url' => $item['url'],
                'menu_type' => $item['menu_type'],
                'parent_id' => $parentId,
                'order' => $item['order'],
                'active' => true,
                'location' => 'storefront',
                'extension_code' => 'service',
                'updated_at' => $now,
            ];

            $existing = DB::table('menu_items')
                ->where('key', $item['key'])
                ->where('location', 'storefront')
                ->first();

            if ($existing) {
                DB::table('menu_items')->where('id', $existing->id)->update($row);
                $ids[$item['key']] = $existing->id;

                continue;
            }

            $ids[$item['key']] = DB::table('menu_items')->insertGetId(
                array_merge($row, [
                    'key' => $item['key'],
                    'created_at' => $now,
                ])
            );
        }
    }
}
