<?php

use App\Models\User;
use Cartxis\Product\Models\ProductImage;
use Cartxis\Setup\Database\Seeders\PersianDemoSeeder;
use Cartxis\Setup\Services\DemoDataService;
use Database\Seeders\OrderSeeder;
use Database\Seeders\TransactionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * The Dari demo shop. This is what an owner gets when they pick the Afghan
 * store during setup, so the whole catalogue has to land in one go.
 */
test('the Dari demo store fills the shop with a full Afghan catalogue', function () {
    $this->seed(PersianDemoSeeder::class);

    expect(DB::table('categories')->count())->toBe(8)
        ->and(DB::table('brands')->count())->toBe(8)
        ->and(DB::table('products')->count())->toBe(24)
        ->and(DB::table('product_images')->count())->toBe(24)
        ->and(DB::table('category_product')->count())->toBe(24)
        ->and(DB::table('service_categories')->count())->toBe(4)
        ->and(DB::table('services')->count())->toBe(10)
        ->and(DB::table('pages')->count())->toBe(5);
});

test('the demo catalogue is written in Dari, not English', function () {
    $this->seed(PersianDemoSeeder::class);

    $this->assertDatabaseHas('categories', ['slug' => 'groceries', 'name' => 'مواد غذایی']);
    $this->assertDatabaseHas('services', ['slug' => 'home-cleaning', 'name' => 'پاککاری کامل خانه']);
    $this->assertDatabaseHas('pages', ['url_key' => 'contact-us', 'title' => 'تماس با ما']);

    // Every visible name and description is in the Arabic/Persian script range,
    // so no English placeholder slipped through.
    foreach (DB::table('products')->get(['name', 'description']) as $product) {
        expect($product->name)->toMatch('/[\x{0600}-\x{06FF}]/u')
            ->and($product->description)->toMatch('/[\x{0600}-\x{06FF}]/u');
    }
});

test('the demo shop has orders and transactions to look at', function () {
    $this->seed(PersianDemoSeeder::class);
    $this->seed(OrderSeeder::class);
    $this->seed(TransactionSeeder::class);

    expect(DB::table('orders')->count())->toBeGreaterThan(0)
        ->and(DB::table('order_items')->count())->toBeGreaterThan(0);
});

test('the demo accounts can sign in and have customer profiles', function () {
    $this->seed(PersianDemoSeeder::class);

    $owner = User::where('email', 'demo@asaan.af')->first();

    expect($owner)->not->toBeNull()
        ->and($owner->role)->toBe('admin')
        ->and($owner->is_active)->toBeTrue()
        ->and(Hash::check('Asaan@2026', $owner->password))->toBeTrue();

    $shopper = User::where('email', 'shopper@asaan.af')->first();

    expect($shopper)->not->toBeNull()
        ->and($shopper->role)->toBe('customer')
        // A customer row, so the customer list and reports have somebody in them.
        ->and(DB::table('customers')->where('user_id', $shopper->id)->exists())->toBeTrue();

    foreach (['fatima@asaan.af' => 'فاطمه', 'karim@asaan.af' => 'کریم'] as $email => $firstName) {
        $buyer = User::where('email', $email)->firstOrFail();

        $this->assertDatabaseHas('customers', [
            'user_id' => $buyer->id,
            'first_name' => $firstName,
        ]);
    }
});

test('the demo product photos are bundled with the app, not loaded from an outside site', function () {
    $this->seed(PersianDemoSeeder::class);

    $images = DB::table('product_images')->get(['path', 'thumbnail_path']);

    expect($images)->not->toBeEmpty();

    foreach ($images as $image) {
        expect($image->path)->toStartWith('/images/products/')
            ->and($image->thumbnail_path)->toStartWith('/images/products/')
            ->and(is_file(public_path(ltrim($image->path, '/'))))->toBeTrue();
    }
});

test('image links resolve for outside links, bundled files and storage files', function () {
    expect(ProductImage::urlFor('https://example.com/a.jpg'))->toBe('https://example.com/a.jpg')
        ->and(ProductImage::urlFor('/images/products/a.jpg'))->toContain('/images/products/a.jpg')
        ->and(ProductImage::urlFor('/images/products/a.jpg'))->not->toContain('/storage/')
        ->and(ProductImage::urlFor('products/a.jpg'))->toContain('/storage/products/a.jpg')
        ->and(ProductImage::urlFor(null))->toBeNull();
});

test('the demo store can be seeded again without duplicating anything', function () {
    $this->seed(PersianDemoSeeder::class);
    $this->seed(PersianDemoSeeder::class);

    expect(DB::table('products')->count())->toBe(24)
        ->and(DB::table('product_images')->count())->toBe(24)
        ->and(DB::table('category_product')->count())->toBe(24)
        ->and(DB::table('services')->count())->toBe(10)
        ->and(DB::table('customers')->count())->toBe(3)
        ->and(User::where('email', 'shopper@asaan.af')->count())->toBe(1);
});

test('the Afghan store is offered first during setup', function () {
    $types = app(DemoDataService::class)->getBusinessTypes();

    expect($types)->not->toBeEmpty()
        ->and($types[0]['id'])->toBe('persian');
});
