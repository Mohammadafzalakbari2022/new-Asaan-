<?php

namespace Cartxis\Identity;

use Cartxis\Identity\Console\PurgeIdentityDocuments;
use Cartxis\Identity\Services\IdentityConfig;
use Cartxis\Identity\Services\IdentityCrypto;
use Cartxis\Identity\Services\IdentityEarningBridge;
use Cartxis\Identity\Services\IdentityImageStore;
use Cartxis\Identity\Services\IdentityRetention;
use Cartxis\Identity\Services\IdentityService;
use Cartxis\Identity\Services\IdentitySettings;
use Cartxis\Identity\Services\IdentityStatus;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/Config/identity.php', 'identity');

        $this->app->singleton(IdentityConfig::class);
        $this->app->singleton(IdentityCrypto::class);
        $this->app->singleton(IdentitySettings::class);
        $this->app->singleton(IdentityImageStore::class);
        $this->app->singleton(IdentityStatus::class);
        $this->app->singleton(IdentityRetention::class);
        $this->app->singleton(IdentityEarningBridge::class);
        $this->app->singleton(IdentityService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        // Only the admin routes are registered from here. The customer routes are
        // required from inside the storefront's account group, the same way the
        // referral programme's are, so they inherit the shop's theme layout,
        // session handling and auth middleware instead of building a parallel set
        // of routes with none of that.
        $this->loadRoutesFrom(__DIR__.'/Routes/admin.php');

        $this->registerRateLimiters();

        if ($this->app->runningInConsole()) {
            $this->commands([PurgeIdentityDocuments::class]);
        }
    }

    /**
     * Submissions are rate limited per account, not per IP address: the person
     * being stopped is the one who owns several accounts, and they all come
     * through one address.
     */
    protected function registerRateLimiters(): void
    {
        RateLimiter::for('identity-submit', function (Request $request) {
            $key = $this->limiterKey($request);

            return Limit::perDay((int) config('identity.max_submissions_per_day', 3))->by($key)
                ->response(function () {
                    return response()->json([
                        'message' => __('Too many attempts. Please try again tomorrow.'),
                    ], 429);
                });
        });
    }

    protected function limiterKey(Request $request): string
    {
        return 'identity-submit|'.($request->user()?->getAuthIdentifier() ?? $request->ip());
    }
}