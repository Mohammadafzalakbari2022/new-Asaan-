<?php

declare(strict_types=1);

namespace Cartxis\Setup\Database\Seeders;

use App\Models\User;
use Cartxis\Product\Models\Product;
use Cartxis\Service\Models\Service;
use Cartxis\Service\Models\ServiceCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * A ready-to-sell Afghan store, written in Dari (Persian).
 *
 * The other demo seeders are English and priced for a generic market. This one
 * gives the shop a real Afghan catalogue: household categories, a handful of
 * products people actually buy, a services book (cleaning, repairs, beauty,
 * moving), store pages, and two login accounts to try it with.
 *
 * Prices are written straight in AFN, because AFN is the store's base currency.
 * Product photos are stable public image links, so the seed never has to reach
 * the network while it runs -- the pictures load in the visitor's browser.
 */
class PersianDemoSeeder extends Seeder
{
    /**
     * Run the database seeds for the Persian (Dari) business type.
     */
    public function run(): void
    {
        $this->seedCategories();
        $this->seedBrands();
        $this->seedProducts();
        $this->seedServiceCategories();
        $this->seedServices();
        $this->seedPages();
        $this->seedBlocks();
        $this->seedDemoAccounts();
    }

    /**
     * The shop's product categories, in Dari.
     */
    private function seedCategories(): void
    {
        $categories = [
            ['name' => 'موبایل و تبلت', 'slug' => 'mobile-tablets', 'description' => 'جدیدترین موبایل‌ها و تبلت‌ها از برندهای معتبر'],
            ['name' => 'لپ‌تاپ و کمپیوتر', 'slug' => 'laptops-computers', 'description' => 'لپ‌تاپ و کمپیوترهای قدرتمند برای کار و تحصیل'],
            ['name' => 'لوازم خانگی', 'slug' => 'home-appliances', 'description' => 'یخچال، ماشین لباس‌شویی و سایر لوازم خانه'],
            ['name' => 'پوشاک و البسه', 'slug' => 'clothing', 'description' => 'لباس مردانه، زنانه و اطفال با کیفیت خوب'],
            ['name' => 'مواد غذایی', 'slug' => 'groceries', 'description' => 'مواد اولیه و خواربار مورد نیاز روزمره'],
            ['name' => 'زیبایی و صحت', 'slug' => 'beauty-health', 'description' => 'محصولات زیبایی، بهداشتی و صحی'],
            ['name' => 'ورزش و تفریح', 'slug' => 'sports', 'description' => 'لوازم ورزشی و سرگرمی برای همه'],
            ['name' => 'کتاب و لوازم تحریر', 'slug' => 'books-stationery', 'description' => 'کتاب، قلم و لوازم مورد نیاز مکتب و دفتر'],
        ];

        foreach ($categories as $index => $category) {
            DB::table('categories')->updateOrInsert(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'slug' => $category['slug'],
                    'description' => $category['description'],
                    'status' => 'enabled',
                    'show_in_menu' => true,
                    'sort_order' => $index + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    /**
     * The brands the shop carries.
     */
    private function seedBrands(): void
    {
        $brands = [
            ['name' => 'سامسونگ', 'slug' => 'samsung', 'is_featured' => true],
            ['name' => 'اپل', 'slug' => 'apple', 'is_featured' => true],
            ['name' => 'هوآوی', 'slug' => 'huawei', 'is_featured' => true],
            ['name' => 'شیائومی', 'slug' => 'xiaomi', 'is_featured' => true],
            ['name' => 'سونی', 'slug' => 'sony', 'is_featured' => false],
            ['name' => 'ال‌جی', 'slug' => 'lg', 'is_featured' => false],
            ['name' => 'فیلیپس', 'slug' => 'philips', 'is_featured' => false],
            ['name' => 'نایکی', 'slug' => 'nike', 'is_featured' => false],
        ];

        foreach ($brands as $brand) {
            DB::table('brands')->updateOrInsert(
                ['slug' => $brand['slug']],
                [
                    'name' => $brand['name'],
                    'slug' => $brand['slug'],
                    'is_featured' => $brand['is_featured'],
                    'status' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    /**
     * The catalogue. Prices are in AFN, the store's base currency.
     */
    private function seedProducts(): void
    {
        $products = [
            ['name' => 'موبایل سامسونگ گلکسی A55', 'sku' => 'PH-SAM-A55', 'price' => 42000, 'category' => 'mobile-tablets', 'brand' => 'samsung', 'featured' => true, 'qty' => 40, 'short' => 'سامسونگ گلکسی A55 - ۲۵۶ گیگابایت', 'desc' => 'موبایل سامسونگ گلکسی A55 با نمایشگر Super AMOLED، دوربین ۵۰ میگاپیکسل و بتری با عمر طولانی. مناسب برای کار و سرگرمی روزمره.'],
            ['name' => 'موبایل شیائومی ردمی نوت ۱۳ پرو', 'sku' => 'PH-XIA-RN13P', 'price' => 28500, 'category' => 'mobile-tablets', 'brand' => 'xiaomi', 'featured' => true, 'qty' => 60, 'short' => 'ردمی نوت ۱۳ پرو - ۲۵۶ گیگابایت', 'desc' => 'موبایل شیائومی ردمی نوت ۱۳ پرو با دوربین ۲۰۰ میگاپیکسل، شارژ سریع ۶۷ وات و نمایشگر روان ۱۲۰ هرتز.'],
            ['name' => 'موبایل هوآوی نوا ۱۲', 'sku' => 'PH-HUA-N12', 'price' => 31000, 'category' => 'mobile-tablets', 'brand' => 'huawei', 'featured' => false, 'qty' => 35, 'short' => 'هوآوی نوا ۱۲ - ۲۵۶ گیگابایت', 'desc' => 'موبایل هوآوی نوا ۱۲ با طراحی شیک، دوربین دوگانه و بتری پرظرفیت. گزینه‌ای مطمئن با قیمت مناسب.'],
            ['name' => 'تبلت سامسونگ گلکسی تب A9', 'sku' => 'TB-SAM-A9', 'price' => 22000, 'category' => 'mobile-tablets', 'brand' => 'samsung', 'featured' => false, 'qty' => 45, 'short' => 'گلکسی تب A9 - ۱۲۸ گیگابایت', 'desc' => 'تبلت سامسونگ گلکسی تب A9 با نمایشگر ۸.۷ اینچی و بتری طویل‌العمر. عالی برای مطالعه و تماشای فیلم.'],
            ['name' => 'لپ‌تاپ اسوس ویووبوک ۱۵', 'sku' => 'LP-ASU-VB15', 'price' => 55000, 'category' => 'laptops-computers', 'brand' => null, 'featured' => true, 'qty' => 25, 'short' => 'اسوس ویووبوک ۱۵ - Core i5', 'desc' => 'لپ‌تاپ اسوس ویووبوک با پروسسور Core i5، رم ۱۶ گیگابایت و هارد SSD ۵۱۲ گیگابایت. مناسب مکتب، دفتر و خانه.'],
            ['name' => 'لپ‌تاپ اچ‌پی پاولیان', 'sku' => 'LP-HP-PAV', 'price' => 78000, 'category' => 'laptops-computers', 'brand' => null, 'featured' => true, 'qty' => 18, 'short' => 'اچ‌پی پاولیان - Core i7', 'desc' => 'لپ‌تاپ اچ‌پی پاولیان با پروسسور Core i7، رم ۱۶ گیگابایت و گرافیک اختصاصی. برای کارهای سنگین و طراحی.'],
            ['name' => 'یخچال سامسونگ ۱۸ فوت', 'sku' => 'HA-SAM-F18', 'price' => 95000, 'category' => 'home-appliances', 'brand' => 'samsung', 'featured' => true, 'qty' => 12, 'short' => 'یخچال سامسونگ ۱۸ فوت', 'desc' => 'یخچال سامسونگ با ظرفیت ۱۸ فوت، سیستم سرمایش مطمئن و مصرف برق پایین. مناسب خانواده‌های بزرگ.'],
            ['name' => 'ماشین لباس‌شویی ال‌جی', 'sku' => 'HA-LG-WM7', 'price' => 62000, 'category' => 'home-appliances', 'brand' => 'lg', 'featured' => false, 'qty' => 15, 'short' => 'ال‌جی ۷ کیلوگرام', 'desc' => 'ماشین لباس‌شویی ال‌جی با ظرفیت ۷ کیلوگرام، برنامه‌های متنوع شستوشو و موتور کم‌صدا.'],
            ['name' => 'آشپزخانه برقی فیلیپس', 'sku' => 'HA-PHI-COOK', 'price' => 8500, 'category' => 'home-appliances', 'brand' => 'philips', 'featured' => false, 'qty' => 30, 'short' => 'آشپزخانه برقی فیلیپس', 'desc' => 'آشپزخانه برقی فیلیپس با دو شعله، ایمنی بالا و طراحی جمع‌وجور. مناسب آشپزخانه‌های کوچک.'],
            ['name' => 'پیراهن مردانه نخی', 'sku' => 'CL-M-SHIRT', 'price' => 1200, 'category' => 'clothing', 'brand' => null, 'featured' => false, 'qty' => 120, 'short' => 'پیراهن نخی مردانه', 'desc' => 'پیراهن مردانه از پارچه نخی درجه اول، راحت و مناسب فصل گرما. در اندازه‌های متنوع موجود است.'],
            ['name' => 'کت چرم زنانه', 'sku' => 'CL-W-JACKET', 'price' => 4500, 'category' => 'clothing', 'brand' => null, 'featured' => true, 'qty' => 40, 'short' => 'کت چرم زنانه', 'desc' => 'کت چرم زنانه با طراحی مدرن و دوخت باکیفیت. مناسب فصل سرد و استایل روزمره.'],
            ['name' => 'کتانی ورزشی نایکی', 'sku' => 'CL-NK-SHOE', 'price' => 3200, 'category' => 'clothing', 'brand' => 'nike', 'featured' => true, 'qty' => 70, 'short' => 'کتانی نایکی - یونیسکس', 'desc' => 'کتانی ورزشی نایکی با کفی نرم و سبک، مناسب پیاده‌روی و ورزش روزانه.'],
            ['name' => 'برنج سیله', 'sku' => 'GR-RICE', 'price' => 2600, 'category' => 'groceries', 'brand' => null, 'featured' => false, 'qty' => 200, 'short' => 'برنج سیله - ۲۴ کیلوگرام', 'desc' => 'برنج سیله ممتاز، خوش‌عطر و بلند. بسته‌بندی ۲۴ کیلوگرامی، مناسب مصرف خانواده.'],
            ['name' => 'روغن آفتاب‌گرد', 'sku' => 'GR-OIL', 'price' => 950, 'category' => 'groceries', 'brand' => null, 'featured' => false, 'qty' => 150, 'short' => 'روغن آفتاب‌گرد - ۵ لیتر', 'desc' => 'روغن آفتاب‌گرد خالص، مناسب پخت‌وپز روزانه. بسته‌بندی ۵ لیتری.'],
            ['name' => 'بسته آرد ۵۰ کیلویی', 'sku' => 'GR-FLOUR', 'price' => 2400, 'category' => 'groceries', 'brand' => null, 'featured' => false, 'qty' => 180, 'short' => 'آرد ۵۰ کیلوگرام', 'desc' => 'آرد گندم مرغوب، الک‌شده و مناسب پخت نان. بسته‌بندی ۵۰ کیلوگرامی.'],
            ['name' => 'صابون زیبایی گیاهی', 'sku' => 'BH-SOAP', 'price' => 150, 'category' => 'beauty-health', 'brand' => null, 'featured' => false, 'qty' => 300, 'short' => 'صابون گیاهی', 'desc' => 'صابون گیاهی با عصاره طبیعی، ملایم برای پوست و مناسب استفاده روزمره.'],
            ['name' => 'کرم مرطوب‌کننده دست', 'sku' => 'BH-CREAM', 'price' => 450, 'category' => 'beauty-health', 'brand' => null, 'featured' => false, 'qty' => 220, 'short' => 'کرم دست و صورت', 'desc' => 'کرم مرطوب‌کننده دست و صورت با ویتامین E، مناسب پوست‌های خشک و حساس.'],
            ['name' => 'دستگاه فشار خون دیجیتل', 'sku' => 'BH-BP', 'price' => 2800, 'category' => 'beauty-health', 'brand' => 'philips', 'featured' => true, 'qty' => 50, 'short' => 'دستگاه فشار خون دیجیتل', 'desc' => 'دستگاه اندازه‌گیری فشار خون دیجیتل با نمایشگر واضح و دقت بالا. مناسب استفاده خانگی.'],
            ['name' => 'توپ فوتبال حرفه‌ای', 'sku' => 'SP-BALL', 'price' => 850, 'category' => 'sports', 'brand' => 'nike', 'featured' => false, 'qty' => 140, 'short' => 'توپ فوتبال', 'desc' => 'توپ فوتبال با دوخت مقاوم و جلد چرمی مصنوعی، مناسب زمین‌های خاکی و چمنی.'],
            ['name' => 'دمبل فلزی ۱۰ کیلویی', 'sku' => 'SP-DUMBBELL', 'price' => 1500, 'category' => 'sports', 'brand' => null, 'featured' => false, 'qty' => 90, 'short' => 'دمبل ۱۰ کیلوگرام', 'desc' => 'دمبل فلزی ۱۰ کیلوگرامی با دسته ضدلغزش، مناسب تمرین در خانه و باشگاه.'],
            ['name' => 'چادر کوه‌نوردی دو نفره', 'sku' => 'SP-TENT', 'price' => 6500, 'category' => 'sports', 'brand' => null, 'featured' => false, 'qty' => 35, 'short' => 'چادر کوه‌نوردی', 'desc' => 'چادر کوه‌نوردی دو نفره، ضدآب و سبک. مناسب سفر و کمپینگ در فضای آزاد.'],
            ['name' => 'کتاب داستان‌های کوتاه دری', 'sku' => 'BK-STORIES', 'price' => 350, 'category' => 'books-stationery', 'brand' => null, 'featured' => false, 'qty' => 160, 'short' => 'مجموعه داستان‌های کوتاه', 'desc' => 'مجموعه داستان‌های کوتاه به زبان دری، مناسب تقویت مهارت خواندن و مطالعه.'],
            ['name' => 'بسته قلم هفت رنگ', 'sku' => 'BK-PENS', 'price' => 220, 'category' => 'books-stationery', 'brand' => null, 'featured' => false, 'qty' => 250, 'short' => 'قلم هفت رنگ', 'desc' => 'بسته قلم رنگی با هفت رنگ مختلف، مناسب مکتب، دفتر و نقاشی اطفال.'],
            ['name' => 'کاپی چهل برگ', 'sku' => 'BK-COPY', 'price' => 90, 'category' => 'books-stationery', 'brand' => null, 'featured' => false, 'qty' => 400, 'short' => 'کاپی ۴۰ برگی', 'desc' => 'کاپی چهل برگی با کاغذ مرغوب و خط کشی واضح. مناسب مکتب و کار روزمره.'],
        ];

        foreach ($products as $product) {
            $categoryId = DB::table('categories')->where('slug', $product['category'])->value('id');
            $brandId = $product['brand']
                ? DB::table('brands')->where('slug', $product['brand'])->value('id')
                : null;

            $existingId = DB::table('products')->where('sku', $product['sku'])->value('id');

            if ($existingId) {
                DB::table('products')->where('id', $existingId)->update([
                    'name' => $product['name'],
                    'price' => $product['price'],
                    'description' => $product['desc'],
                    'short_description' => $product['short'],
                    'updated_at' => now(),
                ]);

                $productId = $existingId;
            } else {
                $productId = DB::table('products')->insertGetId([
                    'name' => $product['name'],
                    'sku' => $product['sku'],
                    'slug' => 'product-'.$product['sku'],
                    'type' => 'simple',
                    'price' => $product['price'],
                    'description' => $product['desc'],
                    'short_description' => $product['short'],
                    'status' => 'enabled',
                    'visibility' => 'visible',
                    'featured' => $product['featured'],
                    'new' => true,
                    'quantity' => $product['qty'],
                    'manage_stock' => true,
                    'stock_status' => 'in_stock',
                    'min_quantity' => 1,
                    'brand_id' => $brandId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($productId && $categoryId) {
                DB::table('category_product')->insertOrIgnore([
                    'category_id' => $categoryId,
                    'product_id' => $productId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($productId && ! DB::table('product_images')->where('product_id', $productId)->exists()) {
                $imagePath = 'https://picsum.photos/seed/'.$product['sku'].'/800/800';

                $imageId = DB::table('product_images')->insertGetId([
                    'product_id' => $productId,
                    'path' => $imagePath,
                    'thumbnail_path' => $imagePath,
                    'alt_text' => $product['name'],
                    'position' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('products')->where('id', $productId)->update(['main_image_id' => $imageId]);
            }
        }
    }

    /**
     * The services book the store takes bookings from, in Dari.
     */
    private function seedServiceCategories(): void
    {
        $categories = [
            ['name' => 'خدمات خانه', 'slug' => 'home-services', 'description' => 'پاککاری، شستوشو و کارهای روزمره خانه'],
            ['name' => 'خدمات تعمیرات', 'slug' => 'repair-services', 'description' => 'تعمیر برقی، لوله‌کشی، نجاری و سایر تعمیرات'],
            ['name' => 'خدمات زیبایی', 'slug' => 'beauty-services', 'description' => 'آرایش، اصلاح و مراقبت از زیبایی'],
            ['name' => 'خدمات نقلیه و باربری', 'slug' => 'transport-services', 'description' => 'اسباب‌کشی، باربری و انتقال اثاثیه'],
        ];

        foreach ($categories as $index => $category) {
            ServiceCategory::updateOrCreate(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'status' => 'enabled',
                    'show_in_menu' => true,
                    'sort_order' => $index + 1,
                ]
            );
        }
    }

    /**
     * The bookable services, each wired to a category.
     */
    private function seedServices(): void
    {
        $services = [
            ['name' => 'پاککاری کامل خانه', 'slug' => 'home-cleaning', 'category' => 'home-services', 'price' => 3000, 'unit' => 'per_job', 'minutes' => 180, 'featured' => true, 'short' => 'پاککاری کامل اتاق‌ها، آشپزخانه و تشناب', 'desc' => 'تیم پاککاری ما تمام اتاق‌ها، آشپزخانه و تشناب را به‌طور کامل پاک می‌کند. مواد پاک‌کننده همراه تیم است.', 'includes' => ['پاککاری اتاق‌ها و راهرو', 'پاککاری آشپزخانه', 'شستوشوی تشناب', 'گردگیری کامل'], 'excludes' => ['رنگ‌مالی دیوار', 'تعمیرات']],
            ['name' => 'شستوشوی فرش و قالین', 'slug' => 'carpet-washing', 'category' => 'home-services', 'price' => 2500, 'unit' => 'per_job', 'minutes' => 240, 'featured' => true, 'short' => 'شستوشوی قالین با مواد مخصوص', 'desc' => 'شستوشوی قالین و فرش با مواد مخصوص، خشک کردن و تحویل دوباره به خانه شما.', 'includes' => ['شستوشوی قالین', 'خشک کردن', 'بسته‌بندی'], 'excludes' => ['ترمیم قالین']],
            ['name' => 'رنگ‌مالی دیوار', 'slug' => 'wall-painting', 'category' => 'home-services', 'price' => 45, 'unit' => 'per_sqm', 'minutes' => 480, 'featured' => false, 'short' => 'رنگ‌مالی دیوار به ازای هر متر مربع', 'desc' => 'رنگ‌مالی دیوارها با رنگ باکیفیت. قیمت به ازای هر متر مربع قابل محاسبه است.', 'includes' => ['آماده‌سازی سطح', 'رنگ‌مالی'], 'excludes' => ['رنگ و مواد (به‌صورت جداگانه)']],
            ['name' => 'تعمیر برقی خانه', 'slug' => 'electrical-repair', 'category' => 'repair-services', 'price' => 1500, 'unit' => 'per_hour', 'minutes' => 60, 'featured' => false, 'short' => 'تعمیر سیستم برقی خانه', 'desc' => 'تشخیص و تعمیر مشکلات برقی خانه: پریز، سوییچ، فیوز و روشنایی.', 'includes' => ['تشخیص مشکل', 'تعمیرات', 'تست نهایی'], 'excludes' => ['خرید قطعات یدکی']],
            ['name' => 'لوله‌کشی و تنظیم تانک آب', 'slug' => 'plumbing-water-tank', 'category' => 'repair-services', 'price' => 2000, 'unit' => 'per_job', 'minutes' => 120, 'featured' => false, 'short' => 'لوله‌کشی و تنظیم تانک آب', 'desc' => 'تعمیر لوله‌کشی، باز کردن گرفتگی و تنظیم و پاککاری تانک آب.', 'includes' => ['تعمیر لوله', 'پاککاری تانک', 'کنترل فشار آب'], 'excludes' => ['خرید لوله و اتصالات']],
            ['name' => 'تعمیر موبایل و تبلت', 'slug' => 'mobile-repair', 'category' => 'repair-services', 'price' => 1200, 'unit' => 'per_job', 'minutes' => 90, 'featured' => true, 'short' => 'تعمیر موبایل و تبلت در محل', 'desc' => 'تعمیر نمایشگر، بتری، شارژ و سایر مشکلات موبایل و تبلت.', 'includes' => ['تشخیص', 'تعمیر', 'ضمانت ۳۰ روزه'], 'excludes' => ['خرید صفحه یا بتری نو']],
            ['name' => 'نجاری و درودگری', 'slug' => 'carpentry', 'category' => 'repair-services', 'price' => 1800, 'unit' => 'per_hour', 'minutes' => 120, 'featured' => false, 'short' => 'کارهای نجاری و درودگری', 'desc' => 'تعمیر درها، کمدها و ساخت قطعات چوبی مطابق اندازه شما.', 'includes' => ['اندازه‌گیری', 'ساخت و نصب', 'پاککاری محل'], 'excludes' => ['خرید چوب']],
            ['name' => 'اصلاح و آرایش عروس', 'slug' => 'bridal-makeup', 'category' => 'beauty-services', 'price' => 8000, 'unit' => 'per_job', 'minutes' => 180, 'featured' => true, 'short' => 'پکیج کامل آرایش عروس', 'desc' => 'آرایش و اصلاح کامل عروس توسط متخصصان، شامل مشوره قبل از مراسم.', 'includes' => ['آرایش صورت', 'بندی مو', 'مشوره'], 'excludes' => ['کرایه لباس']],
            ['name' => 'کوتاهی مو (آقایان)', 'slug' => 'haircut-men', 'category' => 'beauty-services', 'price' => 300, 'unit' => 'per_job', 'minutes' => 30, 'featured' => false, 'short' => 'کوتاهی موی آقایان', 'desc' => 'کوتاهی مو با استفاده از وسایل بهداشتی و الگوی دلخواه شما.', 'includes' => ['کوتاهی مو', 'اصلاح صورت'], 'excludes' => ['رنگ و مواد']],
            ['name' => 'باربری و اسباب‌کشی', 'slug' => 'moving-service', 'category' => 'transport-services', 'price' => 5000, 'unit' => 'per_job', 'minutes' => 240, 'featured' => true, 'short' => 'اسباب‌کشی شهری', 'desc' => 'کارگر و موتر باربری برای انتقال اثاثیه شما در داخل شهر.', 'includes' => ['کارگرهای ماهر', 'موتر باربری', 'بسته‌بندی اساسی'], 'excludes' => ['بیمه اثاثیه قیمتی']],
        ];

        foreach ($services as $index => $row) {
            $category = ServiceCategory::where('slug', $row['category'])->first();

            Service::updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'service_category_id' => $category?->id,
                    'name' => $row['name'],
                    'short_description' => $row['short'],
                    'description' => $row['desc'],
                    'includes' => $row['includes'],
                    'excludes' => $row['excludes'],
                    'price' => $row['price'],
                    'price_unit' => $row['unit'],
                    'duration_minutes' => $row['minutes'],
                    'service_area' => 'کابل، هرات، مزار شریف، جلال‌آباد',
                    'status' => 'enabled',
                    'featured' => $row['featured'],
                    'booking_enabled' => true,
                    'sort_order' => $index + 1,
                ]
            );
        }
    }

    /**
     * The store's pages. "تماس با ما" carries the contact form, because the theme
     * looks for a page whose address contains "contact".
     */
    private function seedPages(): void
    {
        $pages = [
            ['title' => 'درباره ما', 'url_key' => 'about-us', 'content' => '<h1>درباره عصر آسان</h1><p>عصر آسان فروشگاه آنلاین شما در افغانستان است. هدف ما این است که خرید وسائل مورد ضرورت را برای شما ساده، مطمئن و سریع بسازیم.</p>'],
            ['title' => 'تماس با ما', 'url_key' => 'contact-us', 'content' => '<h1>تماس با ما</h1><p>اگر پرسشی دارید، از طریق فورم زیر پیام بفرستید یا ما را در واتس‌اپ پیام کنید. ما در جریان یک روز کاری پاسخ می‌دهیم.</p>'],
            ['title' => 'شرایط و ضوابط', 'url_key' => 'terms-and-conditions', 'content' => '<h1>شرایط و ضوابط</h1><p>استفاده از خدمات عصر آسان به معنی پذیرش شرایط زیر است. لطفاً پیش از خرید مطالعه کنید.</p>'],
            ['title' => 'سیاست بازگشت کالا', 'url_key' => 'returns-policy', 'content' => '<h1>سیاست بازگشت کالا</h1><p>شما می‌توانید تا ۷ روز پس از دریافت کالا، در صورت سالم بودن بسته‌بندی، آن را برگردانید.</p>'],
            ['title' => 'سیاست حریم خصوصی', 'url_key' => 'privacy-policy', 'content' => '<h1>سیاست حریم خصوصی</h1><p>ما معلومات شخصی شما را محفوظ نگه می‌داریم و آن را با کسی به اشتراک نمی‌گذاریم.</p>'],
        ];

        foreach ($pages as $page) {
            DB::table('pages')->updateOrInsert(
                ['url_key' => $page['url_key']],
                [
                    'title' => $page['title'],
                    'url_key' => $page['url_key'],
                    'content' => $page['content'],
                    'status' => 'published',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    /**
     * Front-page content blocks, in Dari.
     */
    private function seedBlocks(): void
    {
        $blocks = [
            ['identifier' => 'persian-hero-banner', 'title' => 'بنر اصلی', 'content' => '<div>خرید آسان و مطمئن از خانه — تحویل سریع در سراسر افغانستان</div>'],
            ['identifier' => 'persian-footer-info', 'title' => 'معلومات پاصفحه', 'content' => '<p>عصر آسان — همراه شما برای خرید راحت.</p>'],
        ];

        foreach ($blocks as $block) {
            DB::table('blocks')->updateOrInsert(
                ['identifier' => $block['identifier']],
                [
                    'title' => $block['title'],
                    'content' => $block['content'],
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    /**
     * Two accounts to try the store with: one owner, one shopper.
     *
     * The shopper also gets a customer record so order/report screens and the
     * sales demo have somebody to sell to.
     */
    private function seedDemoAccounts(): void
    {
        $password = env('DEMO_ACCOUNT_PASSWORD', 'Asaan@2026');

        User::updateOrCreate(
            ['email' => 'demo@asaan.af'],
            [
                'name' => 'مدیر فروشگاه (دیمو)',
                'password' => Hash::make($password),
                'role' => 'admin',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $shopper = User::updateOrCreate(
            ['email' => 'shopper@asaan.af'],
            [
                'name' => 'احمد رحیمی',
                'password' => Hash::make($password),
                'role' => 'customer',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $this->ensureCustomerRecord($shopper, $shopper->name, '+93 700 111 222');

        // A couple more buyers so the customer list and the sales reports are not
        // a table of one.
        foreach ([
            ['email' => 'fatima@asaan.af', 'name' => 'فاطمه نوری', 'phone' => '+93 700 333 444'],
            ['email' => 'karim@asaan.af', 'name' => 'کریم نظری', 'phone' => '+93 700 555 666'],
        ] as $person) {
            $buyer = User::updateOrCreate(
                ['email' => $person['email']],
                [
                    'name' => $person['name'],
                    'password' => Hash::make($password),
                    'role' => 'customer',
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );

            $this->ensureCustomerRecord($buyer, $buyer->name, $person['phone']);
        }

        $this->command?->info('Demo accounts ready.');
        $this->command?->info('Owner:   demo@asaan.af / '.$password);
        $this->command?->info('Shopper: shopper@asaan.af / '.$password);
    }

    /**
     * Give a user a matching customer row, with their name split into first and
     * last. Safe to run again on a shop that already has the customer.
     */
    private function ensureCustomerRecord(User $user, string $name, ?string $phone = null): void
    {
        $firstName = Str::before($name, ' ');
        $lastName = Str::contains($name, ' ') ? Str::after($name, ' ') : '';

        DB::table('customers')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $user->email,
                'phone' => $phone,
                'is_active' => true,
                'is_guest' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
