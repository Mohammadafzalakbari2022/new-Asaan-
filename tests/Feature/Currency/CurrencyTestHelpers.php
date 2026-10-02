<?php

/**
 * Shared fixtures for the currency tests.
 *
 * Loaded once by Pest before anything in this directory runs, so the helper
 * names are prefixed to stay out of the way of the other test files.
 */

use Cartxis\Core\Database\Seeders\CurrencySeeder;
use Cartxis\Core\Models\Currency;
use Cartxis\Core\Support\DisplayCurrency;
use Cartxis\Shop\Models\Order;

if (! function_exists('currencySeed')) {
    /**
     * Run the currency seeder, which is what a real install runs.
     *
     * Takes the test case because seeding goes through the framework, and a
     * plain function has no $this to do it with.
     */
    function currencySeed($test): void
    {
        $test->seed(CurrencySeeder::class);
    }
}

if (! function_exists('currencyRow')) {
    /**
     * A currency row, re-read from the database so the assertions see the
     * stored values rather than whatever the seeder passed in.
     */
    function currencyRow(string $code): Currency
    {
        return Currency::getByCode($code);
    }
}

if (! function_exists('setExchangeRate')) {
    /**
     * The admin changing the rate: exactly what saving the settings form does.
     */
    function setExchangeRate(float $usdToAfn): Currency
    {
        $usd = currencyRow('USD');
        $usd->update(['exchange_rate' => $usdToAfn]);

        return $usd->fresh();
    }
}

if (! function_exists('viewingUsd')) {
    /**
     * Put the shopper in the dollar view, the way the header control does.
     *
     * The session is started first because the display currency lives in it,
     * and an unstarted session reads as "no choice" rather than "USD".
     */
    function viewingUsd(): void
    {
        app('session')->start();

        DisplayCurrency::remember('USD');
    }
}

if (! function_exists('currencySharedProps')) {
    /**
     * The currency props HandleInertiaRequests shares, with every deferred
     * value actually evaluated.
     *
     * The middleware has a second, blunter branch for console runs, so this
     * temporarily presents the app as an HTTP run to get the real props rather
     * than the stand-ins. The flag is put back afterwards.
     *
     * @return array<string, mixed>
     */
    function currencySharedProps(): array
    {
        $app = app();

        $flag = new ReflectionProperty($app, 'isRunningInConsole');
        $flag->setAccessible(true);
        $previous = $flag->getValue($app);
        $flag->setValue($app, false);

        try {
            $request = Illuminate\Http\Request::create('/', 'GET');
            $request->setLaravelSession(app('session')->driver());

            $shared = (new App\Http\Middleware\HandleInertiaRequests())->share($request);

            return array_map(
                fn ($value) => $value instanceof Closure ? $value() : $value,
                $shared
            );
        } finally {
            $flag->setValue($app, $previous);
        }
    }
}

if (! function_exists('currencyOrder')) {
    /**
     * An order whose totals are canonical AFN figures.
     */
    function currencyOrder(array $attributes = []): Order
    {
        static $counter = 0;
        $counter++;

        return Order::create(array_merge([
            'order_number' => 'CUR-' . str_pad((string) $counter, 6, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PENDING,
            'payment_status' => Order::PAYMENT_PENDING,
            'payment_method' => 'cod',
            'subtotal' => 1000,
            'tax' => 0,
            'shipping_cost' => 0,
            'discount' => 0,
            'total' => 1000,
            'customer_email' => 'shopper@example.com',
            'source_channel' => 'web',
        ], $attributes));
    }
}
