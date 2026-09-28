<?php

namespace Cartxis\Referral;

use App\Models\User;
use Cartxis\Referral\Observers\OrderObserver;
use Cartxis\Referral\Observers\UserObserver;
use Cartxis\Referral\Services\ReferralCheckoutService;
use Cartxis\Referral\Services\ReferralCodeService;
use Cartxis\Referral\Services\ReferralCreditService;
use Cartxis\Referral\Services\ReferralEarningService;
use Cartxis\Referral\Services\ReferralLinkService;
use Cartxis\Referral\Services\ReferralSettings;
use Cartxis\Referral\Services\ReferralStatsService;
use Cartxis\Shop\Models\Order;
use Illuminate\Support\ServiceProvider;

class ReferralServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(ReferralSettings::class);
        $this->app->singleton(ReferralCodeService::class);
        $this->app->singleton(ReferralLinkService::class);
        $this->app->singleton(ReferralCreditService::class);
        $this->app->singleton(ReferralEarningService::class);
        $this->app->singleton(ReferralCheckoutService::class);
        $this->app->singleton(ReferralStatsService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/Routes/admin.php');
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        // One watcher for all fifteen places an order is marked paid, and one for
        // every account creation path.
        Order::observe(OrderObserver::class);
        User::observe(UserObserver::class);
    }
}
