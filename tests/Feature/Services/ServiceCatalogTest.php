<?php

use App\Models\User;
use Cartxis\Service\Models\Service;
use Cartxis\Service\Models\ServiceCategory;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

require_once __DIR__ . '/helpers.php';

test('the services menu entries are seeded under Catalog', function () {
    // A brand new store has no menu yet, so the entries must already be there
    // on their own rather than waiting for Catalog to turn up.
    // Scoped to the admin menu, because the storefront uses the same keys.
    $this->assertDatabaseHas('menu_items', ['key' => 'services', 'location' => 'admin']);
    $this->assertDatabaseHas('menu_items', ['key' => 'services-categories', 'location' => 'admin']);
    $this->assertDatabaseHas('menu_items', ['key' => 'services-bookings', 'location' => 'admin']);
    $this->assertDatabaseHas('menu_items', ['key' => 'services-settings', 'location' => 'admin']);

    $this->artisan('db:seed', ['--class' => Cartxis\Admin\Database\Seeders\AdminMenuSeeder::class])
        ->assertSuccessful();

    $catalogId = DB::table('menu_items')->where('key', 'catalog')->value('id');

    expect($catalogId)->not->toBeNull();

    // "migrate --seed" finishes by running db:seed, and the package listens for
    // that to move the entries under Catalog now it exists.
    event(new CommandFinished('db:seed', new ArrayInput([]), new NullOutput, 0));

    // They sit beside Products, in the order the owner should read them.
    foreach (['services', 'services-categories', 'services-bookings', 'services-settings'] as $key) {
        $parentId = DB::table('menu_items')
            ->where('key', $key)
            ->where('location', 'admin')
            ->value('parent_id');

        expect((int) $parentId)->toBe((int) $catalogId);
    }

    $orders = DB::table('menu_items')
        ->where('location', 'admin')
        ->whereIn('key', ['services', 'services-categories', 'services-bookings', 'services-settings'])
        ->orderBy('order')
        ->pluck('key')
        ->all();

    expect($orders)->toBe(['services', 'services-categories', 'services-bookings', 'services-settings']);
});

test('the storefront menus get a Services link', function () {
    // The theme header, mobile drawer and footer are all database-driven, so
    // these rows are the only navigation work needed.
    $this->assertDatabaseHas('menu_items', [
        'key' => 'services-header',
        'location' => 'storefront',
        'menu_type' => 'header',
        'url' => '/services',
    ]);

    $this->assertDatabaseHas('menu_items', [
        'key' => 'services-mobile',
        'location' => 'storefront',
        'menu_type' => 'mobile',
    ]);

    $this->assertDatabaseHas('menu_items', [
        'key' => 'services-footer-book',
        'location' => 'storefront',
        'url' => '/services',
    ]);

    $this->assertDatabaseHas('menu_items', [
        'key' => 'services-footer-track',
        'location' => 'storefront',
        'url' => '/services/track',
    ]);
});

test('the storefront Services links are children of the Services footer group', function () {
    $parentId = DB::table('menu_items')
        ->where('key', 'services-footer')
        ->where('location', 'storefront')
        ->value('id');

    expect($parentId)->not->toBeNull();

    foreach (['services-footer-book', 'services-footer-track'] as $key) {
        $childParent = DB::table('menu_items')
            ->where('key', $key)
            ->where('location', 'storefront')
            ->value('parent_id');

        expect((int) $childParent)->toBe((int) $parentId);
    }
});

test('the admin sidebar and the storefront both keep a Services entry', function () {
    // menu_items.key is unique across the whole table, so the two menus cannot
    // share one row. Run the sync again, the way a second "migrate --seed"
    // would, and check both links survive.
    app(Cartxis\Service\Services\ServiceMenuService::class)->sync();

    $admin = DB::table('menu_items')->where('key', 'services')->where('location', 'admin')->first();
    $header = DB::table('menu_items')->where('key', 'services-header')->where('location', 'storefront')->first();

    expect($admin)->not->toBeNull();
    expect($header)->not->toBeNull();

    // The admin row points at a route; the storefront row points at a URL.
    expect($admin->route)->toBe('admin.services.index');
    expect($header->url)->toBe('/services');
    expect($header->route)->toBeNull();
});

test('the Services link shows up in the storefront header', function () {
    // The theme reads the header from the database, so the row existing is not
    // enough: the rendered page has to actually contain the link, otherwise
    // customers are given no way to find the services at all.
    $this->get('/')
        ->assertOk()
        ->assertSee('Services')
        ->assertSee('/services', false);
});

test('syncing the menu twice does not create duplicates', function () {
    $before = DB::table('menu_items')->where('location', 'storefront')->where('key', 'services-header')->count();

    app(Cartxis\Service\Services\ServiceMenuService::class)->sync();
    app(Cartxis\Service\Services\ServiceMenuService::class)->sync();

    $after = DB::table('menu_items')->where('location', 'storefront')->where('key', 'services-header')->count();

    expect($after)->toBe($before);
});

test('a failed seed leaves the menu alone', function () {
    $before = DB::table('menu_items')
        ->where('key', 'services')
        ->where('location', 'admin')
        ->value('parent_id');

    event(new CommandFinished('db:seed', new ArrayInput([]), new NullOutput, 1));

    expect(DB::table('menu_items')
        ->where('key', 'services')
        ->where('location', 'admin')
        ->value('parent_id'))->toBe($before);
});

test('guests are redirected away from the service admin pages', function () {
    $this->get('/admin/services')->assertStatus(302);
    $this->get('/admin/services/categories')->assertStatus(302);
    $this->get('/admin/services/bookings')->assertStatus(302);
});

test('a customer is kept out of the service admin pages', function () {
    $customer = User::factory()->withoutTwoFactor()->create(['role' => 'customer']);

    // The platform bounces non-admins away rather than showing them a 403 page.
    $this->actingAs($customer, 'admin')
        ->get('/admin/services')
        ->assertStatus(302)
        ->assertDontSee('Emergency Pipe Repair');
});

test('an admin can create a service', function () {
    $admin = serviceAdmin();
    $category = makeServiceCategory(['name' => 'Plumbing']);

    $this->actingAs($admin, 'admin')->post('/admin/services', [
        'name' => 'Emergency Pipe Repair',
        'service_category_id' => $category->id,
        'price' => 4500,
        'price_unit' => 'per_job',
        'status' => 'enabled',
        'booking_enabled' => '1',
    ])->assertRedirect('/admin/services');

    $this->assertDatabaseHas('services', [
        'name' => 'Emergency Pipe Repair',
        'price' => 4500,
        'service_category_id' => $category->id,
    ]);

    // The slug is made from the name so the owner never has to think about it.
    expect(Service::first()->slug)->toBe('emergency-pipe-repair');
});

test('a service cannot be saved without a name or a price', function () {
    $admin = serviceAdmin();

    $this->actingAs($admin, 'admin')->post('/admin/services', [
        'name' => '',
        'price' => '',
        'price_unit' => 'per_job',
        'status' => 'enabled',
    ])->assertSessionHasErrors(['name', 'price']);

    $this->assertDatabaseCount('services', 0);
});

test('a negative price is refused', function () {
    $admin = serviceAdmin();

    $this->actingAs($admin, 'admin')->post('/admin/services', [
        'name' => 'Cheap Job',
        'price' => -50,
        'price_unit' => 'per_job',
        'status' => 'enabled',
    ])->assertSessionHasErrors('price');

    $this->assertDatabaseCount('services', 0);
});

test('an unknown price unit is refused', function () {
    $admin = serviceAdmin();

    $this->actingAs($admin, 'admin')->post('/admin/services', [
        'name' => 'Odd Job',
        'price' => 100,
        'price_unit' => 'per_fortnight',
        'status' => 'enabled',
    ])->assertSessionHasErrors('price_unit');

    $this->assertDatabaseCount('services', 0);
});

test('two services with the same name get different web addresses', function () {
    $admin = serviceAdmin();

    foreach (['House Shifting', 'House Shifting'] as $name) {
        $this->actingAs($admin, 'admin')->post('/admin/services', [
            'name' => $name,
            'price' => 1000,
            'price_unit' => 'per_job',
            'status' => 'enabled',
        ])->assertRedirect();
    }

    expect(Service::pluck('slug')->all())->toBe(['house-shifting', 'house-shifting-2']);
});

test('an admin can update a service without changing its web address', function () {
    $admin = serviceAdmin();
    $service = makeService(['name' => 'Garden Care', 'slug' => 'garden-care']);

    $this->actingAs($admin, 'admin')
        ->put('/admin/services/' . $service->slug, [
            'name' => 'Garden Maintenance',
            'slug' => 'garden-care',
            'price' => 2000,
            'price_unit' => 'per_day',
            'status' => 'enabled',
        ])
        ->assertRedirect('/admin/services');

    $service->refresh();

    expect($service->name)->toBe('Garden Maintenance')
        ->and($service->slug)->toBe('garden-care')
        ->and($service->price)->toBe('2000.00');
});

test('a service with no jobs is deleted outright', function () {
    $admin = serviceAdmin();
    $service = makeService();

    $this->actingAs($admin, 'admin')
        ->delete('/admin/services/' . $service->slug)
        ->assertStatus(302);

    $this->assertSoftDeleted('services', ['id' => $service->id]);
});

test('a service that already has jobs is hidden rather than deleted', function () {
    configureServices();
    $admin = serviceAdmin();
    $service = makeService();
    bookServiceJob($service);

    $this->actingAs($admin, 'admin')
        ->delete('/admin/services/' . $service->slug)
        ->assertStatus(302);

    // The job is a financial record, so the service is hidden instead of removed.
    $this->assertDatabaseHas('services', ['id' => $service->id, 'status' => 'disabled']);
});

test('a service shows its price with the unit the owner chose', function () {
    $service = makeService(['price' => 750, 'price_unit' => 'per_hour']);

    expect($service->price_display)->toBe('750.00 per hour');
});

test('a service duration reads as hours rather than minutes', function () {
    expect(makeService(['duration_minutes' => 45])->duration_display)->toBe('45 min')
        ->and(makeService(['duration_minutes' => 120])->duration_display)->toBe('2 hours')
        ->and(makeService(['duration_minutes' => 90])->duration_display)->toBe('1 hour 30 min')
        ->and(makeService(['duration_label' => 'half a day'])->duration_display)->toBe('half a day');
});

test('a category holding services cannot be deleted', function () {
    $admin = serviceAdmin();
    $category = makeServiceCategory();
    makeService(['service_category_id' => $category->id]);

    $this->actingAs($admin, 'admin')
        ->delete('/admin/services/categories/' . $category->slug)
        ->assertStatus(302);

    $this->assertDatabaseHas('service_categories', ['id' => $category->id]);
    expect(ServiceCategory::find($category->id)->trashed())->toBeFalse();
});

test('a category cannot be filed under its own sub-category', function () {
    $admin = serviceAdmin();
    $parent = makeServiceCategory(['name' => 'Home']);
    $child = makeServiceCategory(['name' => 'Cleaning', 'parent_id' => $parent->id]);

    $this->actingAs($admin, 'admin')
        ->put('/admin/services/categories/' . $parent->slug, [
            'name' => 'Home',
            'parent_id' => $child->id,
            'status' => 'enabled',
        ])
        ->assertSessionHasErrors('parent_id');

    expect($parent->fresh()->parent_id)->toBeNull();
});

test('an admin can bulk hide several services at once', function () {
    $admin = serviceAdmin();
    $one = makeService(['name' => 'One']);
    $two = makeService(['name' => 'Two']);

    $this->actingAs($admin, 'admin')->post('/admin/services/bulk-status', [
        'ids' => [$one->id, $two->id],
        'status' => 'disabled',
    ])->assertStatus(302);

    $this->assertDatabaseHas('services', ['id' => $one->id, 'status' => 'disabled']);
    $this->assertDatabaseHas('services', ['id' => $two->id, 'status' => 'disabled']);
});

test('the services list can be searched and filtered', function () {
    serviceAdmin();

    makeService(['name' => 'Pipe Repair', 'status' => 'enabled']);
    makeService(['name' => 'Window Cleaning', 'status' => 'disabled']);

    $this->actingAs(User::where('role', 'admin')->first(), 'admin')
        ->get('/admin/services?search=Pipe')
        ->assertOk()
        ->assertSee('Pipe Repair')
        ->assertDontSee('Window Cleaning');

    $this->actingAs(User::where('role', 'admin')->first(), 'admin')
        ->get('/admin/services?status=disabled')
        ->assertOk()
        ->assertSee('Window Cleaning')
        ->assertDontSee('Pipe Repair');
});
