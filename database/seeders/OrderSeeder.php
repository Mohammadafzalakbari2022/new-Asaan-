<?php

declare(strict_types=1);

namespace Database\Seeders;

use Cartxis\Product\Models\Product;
use Cartxis\Shop\Models\Order;
use Cartxis\Shop\Models\OrderItem;
use Illuminate\Database\Seeder;

/**
 * Demo orders for a freshly imported catalogue.
 *
 * The setup flow seeds products but no sales, so every admin screen that reads
 * an order (orders, invoices, shipments, reports) opens on an empty table. This
 * creates a spread of realistic orders across the last three months so the
 * screens have something to show.
 *
 * It never invents catalogue data: if no products exist it says so and stops.
 * It also leaves an existing order history untouched, so re-running it cannot
 * duplicate what is already there.
 */
class OrderSeeder extends Seeder
{
    private const ORDER_COUNT = 18;

    private const SHIPPING_COST = 150.0;

    public function run(): void
    {
        if (Order::query()->exists()) {
            $this->command?->warn('Orders already exist; the order seeder only runs on an empty store.');

            return;
        }

        $products = Product::query()->where('status', 'enabled')->get();

        if ($products->isEmpty()) {
            $this->command?->warn('No products found. Import demo data before seeding orders.');

            return;
        }

        $customers = \Cartxis\Customer\Models\Customer::query()->get();

        $outcomes = [
            ['status' => Order::STATUS_COMPLETED, 'payment' => Order::PAYMENT_PAID],
            ['status' => Order::STATUS_COMPLETED, 'payment' => Order::PAYMENT_PAID],
            ['status' => Order::STATUS_COMPLETED, 'payment' => Order::PAYMENT_PAID],
            ['status' => Order::STATUS_PROCESSING, 'payment' => Order::PAYMENT_PAID],
            ['status' => Order::STATUS_PROCESSING, 'payment' => Order::PAYMENT_PAID],
            ['status' => Order::STATUS_PENDING, 'payment' => Order::PAYMENT_PENDING],
            ['status' => Order::STATUS_PENDING, 'payment' => Order::PAYMENT_PENDING],
            ['status' => Order::STATUS_CANCELLED, 'payment' => Order::PAYMENT_FAILED],
        ];

        $methods = ['cod', 'hesabpay', 'bank_transfer'];

        for ($i = 0; $i < self::ORDER_COUNT; $i++) {
            $outcome = $outcomes[array_rand($outcomes)];
            $placedAt = now()->subDays(random_int(0, 88))->subMinutes(random_int(0, 1439));

            $lineItems = $products->random(random_int(1, 3))->map(function (Product $product) {
                $quantity = random_int(1, 3);
                $price = (float) $product->price;

                return [
                    'product' => $product,
                    'quantity' => $quantity,
                    'price' => $price,
                    'total' => round($price * $quantity, 2),
                ];
            });

            $subtotal = round((float) $lineItems->sum('total'), 2);
            $shipping = $outcome['status'] === Order::STATUS_CANCELLED ? 0.0 : self::SHIPPING_COST;
            $total = round($subtotal + $shipping, 2);

            $customer = $customers->isNotEmpty() && random_int(0, 1) === 1
                ? $customers->random()
                : null;

            $order = Order::create([
                'user_id' => $customer?->user_id,
                'customer_id' => $customer?->id,
                'order_number' => $this->uniqueOrderNumber(),
                'status' => $outcome['status'],
                'payment_status' => $outcome['payment'],
                'subtotal' => $subtotal,
                'tax' => 0,
                'shipping_cost' => $shipping,
                'discount' => 0,
                'total' => $total,
                'payment_method' => $methods[array_rand($methods)],
                'shipping_method' => 'standard',
                'customer_email' => $customer?->email,
                'customer_phone' => $customer?->phone,
                'source_channel' => 'web',
                'created_at' => $placedAt,
                'updated_at' => $placedAt,
            ]);

            foreach ($lineItems as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product']->id,
                    'product_sku' => $item['product']->sku,
                    'product_name' => $item['product']->name,
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => $item['total'],
                    'tax_amount' => 0,
                    'discount_amount' => 0,
                ]);
            }
        }

        $this->command?->info('Order seeder created '.self::ORDER_COUNT.' demo orders.');
    }

    private function uniqueOrderNumber(): string
    {
        do {
            $number = Order::generateOrderNumber();
        } while (Order::withTrashed()->where('order_number', $number)->exists());

        return $number;
    }
}
