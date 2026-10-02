<?php

namespace Cartxis\HesabPay\Providers;

use Cartxis\Core\Models\Extension;
use Cartxis\Core\Models\PaymentMethod;
use Cartxis\Core\Services\PaymentGatewayManager;
use Cartxis\HesabPay\Services\HesabPayGateway;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class HesabPayServiceProvider extends ServiceProvider
{
    protected const EXTENSION_CODE = 'cartxis-hesabpay';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../Config/hesabpay.php', 'hesabpay');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Seeded even when the extension is off, so the row is always there for
        // the admin to configure, rather than appearing from nowhere on switch-on.
        $this->seedPaymentMethod();

        if (!$this->isExtensionActive()) {
            return;
        }

        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/admin.php');

        $this->app->make(PaymentGatewayManager::class)->register(new HesabPayGateway());
    }

    /**
     * Determine whether this extension should boot.
     *
     * Mirrors the other gateways: if the extensions table is unavailable yet we
     * boot normally, otherwise the stored row decides.
     */
    protected function isExtensionActive(): bool
    {
        try {
            if (!Schema::hasTable('extensions')) {
                return true;
            }

            $extension = Extension::firstOrCreate(
                ['code' => self::EXTENSION_CODE],
                [
                    'name' => 'HesabPay Payment Gateway',
                    'description' => 'Accept HesabPay wallet, AFN, AfPay and international card payments',
                    'version' => '1.0.0',
                    'author' => 'Cartxis Team',
                    'requires' => [],
                    'config' => [],
                    'installed' => true,
                    'active' => true,
                    'installed_at' => now(),
                ]
            );

            if (!$extension->installed) {
                $extension->update(['installed' => true, 'installed_at' => now()]);
            }

            return (bool) $extension->active;
        } catch (\Throwable $e) {
            return true;
        }
    }

    /**
     * Seed the payment method row.
     *
     * Shipped active and as the store default: a local wallet is the only way
     * most Afghan shoppers can pay online. The keys are still blank, so
     * checkout hides the method again until the owner configures it.
     */
    protected function seedPaymentMethod(): void
    {
        try {
            if (PaymentMethod::where('code', 'hesabpay')->exists()) {
                return;
            }

            PaymentMethod::create([
                'code' => 'hesabpay',
                'name' => 'HesabPay',
                'type' => 'hesabpay',
                'description' => 'Pay with the HesabPay wallet, AfPay card, or an international card.',
                'is_active' => true,
                'is_default' => true,
                // Ahead of Cash on Delivery (1), which is the method it replaces
                // as the preselected option at checkout.
                'sort_order' => 0,
                'configuration' => [
                    'mode' => 'sandbox',
                    'test_api_key' => '',
                    'api_key' => '',
                ],
            ]);
        } catch (\Exception $e) {
            // Expected while migrations are still running, when the payment
            // methods table does not exist yet. The row is seeded on boot after.
        }
    }
}
