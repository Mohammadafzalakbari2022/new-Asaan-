<?php

namespace Cartxis\Service;

use Cartxis\Service\Services\ServiceBookingService;
use Cartxis\Service\Services\ServiceBookingStatusService;
use Cartxis\Service\Services\ServiceMenuService;
use Cartxis\Service\Services\ServiceSettings;
use Cartxis\Service\Services\ServiceSlotService;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\ServiceProvider;

class ServiceServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/Config/service.php', 'service'
        );

        $this->app->singleton(ServiceSettings::class);
        $this->app->singleton(ServiceSlotService::class);
        $this->app->singleton(ServiceBookingStatusService::class);
        $this->app->singleton(ServiceBookingService::class);
        $this->app->singleton(ServiceMenuService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');

        // The admin menu is seeded after migrations on a new store, so the
        // Catalog row only exists by then. Re-place the Services entries once
        // the menu is there, instead of leaving them at the top level.
        // "migrate --seed" runs db:seed as a nested command, so this covers both.
        $this->app['events']->listen(CommandFinished::class, function (CommandFinished $event): void {
            if ($event->command !== 'db:seed' || $event->exitCode !== 0) {
                return;
            }

            $this->app->make(ServiceMenuService::class)->sync();
        });

        // The storefront pages, loaded without a prefix so the URL segment is
        // the owner's choice; "services" is the default.
        $this->loadRoutesFrom(__DIR__ . '/Routes/web.php');

        // Admin screens sit beside Catalog, Products and Categories.
        $this->loadRoutesFrom(__DIR__ . '/Routes/admin.php');

        // Worker screens live inside the existing delivery portal and reuse its
        // guard, session and role check. No second login, no second app.
        $this->loadRoutesFrom(__DIR__ . '/Routes/delivery.php');

        $this->publishes([
            __DIR__ . '/Config/service.php' => config_path('service.php'),
        ], 'service-config');

        $this->publishes([
            __DIR__ . '/Database/Migrations' => database_path('migrations'),
        ], 'service-migrations');
    }
}
