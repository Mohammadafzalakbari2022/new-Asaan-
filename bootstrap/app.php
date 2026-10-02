<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\FrontendMaintenanceMode;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocaleFromCookie;
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
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/*
|--------------------------------------------------------------------------
| What a failed request looks like to the browser
|--------------------------------------------------------------------------
|
| Inertia decides whether it can read a reply by looking at ONE thing: whether
| that reply carries the `X-Inertia` header. A reply without it is a reply
| Inertia cannot understand, so it throws away the page it was showing and puts
| its own error box over the top -- a blank screen with the reply pasted into
| it, on a URL that is otherwise perfectly fine.
|
| Only `Inertia\Response` ever adds that header, and it only does so for replies
| the server built as Inertia pages. Laravel's error pages are ordinary HTML, so
| every failure underneath a page -- a product that no longer exists, a session
| that expired, a database that went away -- arrived with no header and put that
| error box on the screen. That is the whole bug: a broken page should show a
| message, not an unreadable reply.
|
| So errors get rendered as Inertia pages too. Nothing is hidden: the status
| code still comes back as the status code, the exception is still logged, and
| the visitor still sees that the page did not work. Only the shape of the
| failure changes, so the browser can show it the way it shows everything else.
|
*/

/** A plain-English headline for a request that failed. */
$describeStatus = fn (int $status) => match ($status) {
    401 => 'Please sign in to continue',
    403 => 'You do not have access to this page',
    404 => 'We could not find that page',
    419 => 'Your session has expired',
    429 => 'Too many requests, please slow down',
    503 => 'We will be back shortly',
    default => 'Something went wrong',
};

/**
 * A plain-English explanation of a request that failed.
 *
 * Deliberately says nothing about the cause. An exception message can name
 * database tables, file paths and query fragments, and none of that belongs on
 * a page a visitor is reading. The detail goes to the log, where it belongs, and
 * only comes back onto the page when the app is explicitly in debug mode.
 */
$describeFailure = fn (int $status, Throwable $throwable) => config('app.debug')
    ? $throwable->getMessage()
    : match ($status) {
        401 => 'Sign in and try again.',
        403 => 'If you think this is wrong, ask an administrator for access.',
        404 => 'The page may have moved, or the address may have a typo in it.',
        419 => 'Reload the page and try again.',
        429 => 'Wait a moment and try again.',
        503 => 'The shop is being worked on right now. Please try again shortly.',
        default => 'The page could not be loaded. Please try again in a moment.',
    };

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
            SetLocaleFromCookie::class,
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
    ->withExceptions(function (Exceptions $exceptions) use ($describeStatus, $describeFailure) {
        $exceptions->respond(function (Response $response, Throwable $throwable, Request $request) use ($describeStatus, $describeFailure) {
            // Nobody asked for an Inertia page, so an ordinary HTML error page
            // is exactly right.
            if (! $request->header('X-Inertia')) {
                return $response;
            }

            // Already an Inertia reply -- there is nothing to fix.
            if ($response->headers->get('X-Inertia')) {
                return $response;
            }

            // "Reload the whole page." Inertia sends this when the visitor is
            // carrying a stale copy of the built assets, and it has to be left
            // alone: turning it into an error page would stop the browser ever
            // picking up the new build.
            if ($response->headers->get('X-Inertia-Location')) {
                return $response;
            }

            // A redirect or a success is not a failure.
            if ($response->getStatusCode() < 400 || $response->isRedirect()) {
                return $response;
            }

            $status = $response->getStatusCode();

            try {
                // Shared props are dropped on purpose. Nearly all of them are
                // lazy and reach for the database, and the database is often the
                // very thing that has just failed. An error page that fails too
                // tells the visitor nothing.
                Inertia::flushShared();

                $error = Inertia::render('Error', [
                    'status' => $status,
                    'title' => $describeStatus($status),
                    'message' => $describeFailure($status, $throwable),
                    'maintenance' => false,
                ])->toResponse($request);

                $error->setStatusCode($status);

                // The middleware that normally adds this never runs on the
                // failure path, so anything caching the reply would not know to
                // key it apart.
                $error->headers->set('Vary', 'X-Inertia');

                return $error;
            } catch (Throwable $unbuildable) {
                // The error page itself could not be built. The original
                // response is still an honest account of what happened, so send
                // that rather than stacking a second failure on top of it.
                report($unbuildable);

                return $response;
            }
        });
    })->create();
