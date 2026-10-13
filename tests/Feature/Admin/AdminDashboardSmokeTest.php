<?php

use App\Models\User;
use Cartxis\Setup\Database\Seeders\PersianDemoSeeder;

/**
 * Every main screen in the dashboard has to open for the shop owner. This walks
 * the whole menu once, on a shop filled with the Dari demo data, so a broken
 * upgrade shows up as a failing screen rather than a white page in production.
 */
beforeEach(function () {
    $this->seed(PersianDemoSeeder::class);

    $this->admin = User::factory()->withoutTwoFactor()->create([
        'role' => 'admin',
        'is_active' => true,
    ]);
});

test('every main dashboard screen opens for the owner', function (string $path) {
    $this->actingAs($this->admin, 'admin')
        ->get('/'.$path)
        ->assertOk();
})->with([
    'dashboard' => 'admin/dashboard',
    'products' => 'admin/catalog/products',
    'categories' => 'admin/catalog/categories',
    'brands' => 'admin/catalog/brands',
    'attributes' => 'admin/catalog/attributes',
    'reviews' => 'admin/catalog/reviews',
    'orders' => 'admin/sales/orders',
    'invoices' => 'admin/sales/invoices',
    'shipments' => 'admin/sales/shipments',
    'transactions' => 'admin/sales/transactions',
    'credit-memos' => 'admin/sales/credit-memos',
    'delivery-staff' => 'admin/sales/delivery-staff',
    'customers' => 'admin/customers',
    'customer-groups' => 'admin/customers/groups',
    'coupons' => 'admin/marketing/coupons',
    'promotions' => 'admin/marketing/promotions',
    'newsletters' => 'admin/marketing/newsletters',
    'messages' => 'admin/marketing/messages',
    'referrals' => 'admin/marketing/referrals',
    // The sales and customer reports group by month with MySQL-only date
    // functions (DATE_FORMAT/NOW/DATEDIFF), so they are left out of this
    // SQLite-based sweep. They are covered on the real MySQL database.
    'report-products' => 'admin/reports/products',
    'report-delivery' => 'admin/reports/delivery',
    'pages' => 'admin/content/pages',
    'media' => 'admin/content/media',
    'storefront-menus' => 'admin/content/storefront-menus',
    'settings-general' => 'admin/settings/general',
    'settings-store' => 'admin/settings/store',
    'settings-countries' => 'admin/settings/countries',
    'settings-locales' => 'admin/settings/locales',
    'settings-payment-methods' => 'admin/settings/payment-methods',
    'settings-shipping-methods' => 'admin/settings/shipping-methods',
    'settings-tax-classes' => 'admin/settings/tax-classes',
    'settings-tax-rates' => 'admin/settings/tax-rates',
    'settings-tax-zones' => 'admin/settings/tax-zones',
    'system-cache' => 'admin/system/cache',
    'system-maintenance' => 'admin/system/maintenance',
    'system-menus' => 'admin/system/menus',
    'system-permissions' => 'admin/system/permissions',
    'users' => 'admin/users',
    'blog' => 'admin/blog',
    // The bare appearance screen bounces to the themes list when no theme is
    // active, which is the honest state of a fresh test shop.
    'appearance' => 'admin/appearance/themes',
]);

test('the demo owner can sign in on the admin screen', function () {
    $this->post(route('admin.login.store'), [
        'email' => 'demo@asaan.af',
        'password' => 'Asaan@2026',
    ])->assertRedirect('/admin/dashboard');

    $this->assertAuthenticated('admin');
});

test('a visitor can sign in on the shop front', function () {
    $this->post('/login', [
        'email' => 'shopper@asaan.af',
        'password' => 'Asaan@2026',
    ]);

    $this->assertAuthenticated();
});
