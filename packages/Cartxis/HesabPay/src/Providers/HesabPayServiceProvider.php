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
        // Seeded even when the extension is off, so the admin can find and
        // switch it on instead of the option simply not existing.
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
     * Shipped inactive: enabling a payment method is the owner's decision, and
     * an unconfigured gateway would only produce checkout errors.
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
                'is_active' => false,
                'configuration' => [
                    'mode' => 'sandbox',
                    'test_api_key' => '',
                    'api_key' => '',
                ],
                'sort_order' => 3,
            ]);
        } catch (\Exception $e) {
            // Expected while migrations are still running, when the payment
            // methods table does not exist yet. The row is seeded on boot after.
        }
    }
}
