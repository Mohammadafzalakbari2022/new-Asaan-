<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\FrontendMaintenanceMode;
use App\Http\Middleware\HandleInertiaRequests;
use Cartxis\Admin\Http\Middleware\PreventAdminFrontendAccess;
use Cartxis\Admin\Http\Middleware\PreventUserAdminAccess;
use Cartxis\Sales\Http\Middleware\EnsureDeliveryRole;
use Cartxis\Sales\Http\Middleware\RedirectIfDeliveryAuthenticated;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Define API rate limiters
            RateLimiter::for('api', function (Request $request) {
                return $request->user()
                    ? Limit::perMinute(300)->by($request->user()->id)
                    : Limit::perMinute(60)->by($request->ip());
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // We sit behind Render (and Cloudflare in front of that), so the browser only
        // ever talks https. Without this, Laravel ignores the X-Forwarded-Proto header
        // the proxy sends, believes the request arrived over plain http, and every
        // route() it generates comes out as http:// -- which the browser then refuses
        // to submit to from an https page ("Mixed Content ... has been blocked").
        // Laravel only auto-trusts Forge and Vapor hosts, so .onrender.com has to be
        // trusted explicitly. Set TRUSTED_PROXIES='*' to trust any proxy, or list
        // proxy IPs to narrow it down.
        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES', '*'),
            headers: Request::HEADER_X_FORWARDED_PROTO,
        );
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            \Cartxis\Referral\Http\Middleware\CaptureReferralCode::class,
            \Cartxis\Referral\Http\Middleware\ShareReferralData::class,
            FrontendMaintenanceMode::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            PreventAdminFrontendAccess::class, // Prevent admins from accessing frontend
            PreventUserAdminAccess::class,    // Enforce is_active + role on admin guard sessions
            \Cartxis\Core\Http\Middleware\SecurityHeaders::class,
        ]);

        // Add middleware to admin routes to prevent regular users
        $middleware->alias([
            'prevent.user.admin' => PreventUserAdminAccess::class,
            'delivery.access' => EnsureDeliveryRole::class,
            'redirectIfDeliveryAuthenticated' => RedirectIfDeliveryAuthenticated::class,
        ]);

        // Redirect guests based on the guard
        $middleware->redirectGuestsTo(function ($request) {
            if ($request->is('admin/*')) {
                return route('admin.login');
            }
            if ($request->is('delivery/*')) {
                return route('delivery.login');
            }
            return route('login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->boolean('diag')) {
                return response(
                    get_class($e).': '.$e->getMessage()."\n"
                    .$e->getFile().':'.$e->getLine()."\n\n"
                    .$e->getTraceAsString(),
                    500
                )->header('Content-Type', 'text/plain; charset=utf-8');
            }
        });
    })->create();
