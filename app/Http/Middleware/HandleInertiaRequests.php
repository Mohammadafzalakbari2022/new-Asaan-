<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Middleware;
use Cartxis\Core\Models\Currency;
use Cartxis\Core\Services\MenuService;
use Cartxis\Core\Services\SettingService;
use Cartxis\Core\Support\DisplayCurrency;
use Cartxis\Admin\Services\AdminNotificationService;
use Cartxis\Settings\Models\Setting as SystemSetting;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the root view dynamically based on the route.
     */
    public function rootView(Request $request): string
    {
        // Admin routes use the admin template
        if ($request->is('admin/*') || $request->is('admin')) {
            return 'app';
        }

        // Frontend routes use the theme template (same app.blade.php for now)
        return 'app';
    }

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        // Skip database queries when running in console (e.g., during migrations)
        if (app()->runningInConsole()) {
            return array_merge(parent::share($request), [
                'name' => config('app.name'),
                'appVersion' => config('app.version'),
                'locale' => config('app.locale', 'fa'),
                'locales' => [],
                'quote' => ['message' => trim($message), 'author' => trim($author)],
                'auth' => [
                    'user' => null,
                ],
                'menu' => [
                    'admin' => [],
                    'shop' => [],
                ],
                'flash' => \Inertia\Inertia::always(function () use ($request) {
                    return [
                        'success' => null,
                        'error' => null,
                        'warning' => null,
                        'info' => null,
                        'redirect_url' => null,
                    ];
                }),
                'sidebarOpen' => true,
                'ziggy' => fn () => [
                    'location' => $request->url(),
                ],
                // Nothing is querying the database during a migration, so the
                // currency props are left empty rather than risking a query
                // against a table that does not exist yet.
                'currency' => null,
                'currencies' => [],
                'displayCurrency' => null,
                'usdToAfn' => Currency::DEFAULT_USD_TO_AFN,
                'calendar' => null,
            ]);
        }

        $menuService = app(MenuService::class);
        $settingService = app(SettingService::class);
        $adminNotificationService = app(AdminNotificationService::class);

        // Build menu trees with error handling
        $adminMenu = [];
        $shopMenu = [];
        
        try {
            $adminMenu = $menuService->buildTree('admin');
        } catch (\Exception $e) {
            // Silently fail during migration when table doesn't exist
        }
        
        try {
            $shopMenu = $menuService->buildTree('shop');
        } catch (\Exception $e) {
            // Silently fail during migration when table doesn't exist
        }

        return array_merge(parent::share($request), [
            'name' => config('app.name'),
            'appVersion' => config('app.version'),
            'locale' => app()->getLocale(),
            'locales' => function () {
                try {
                    return \Cartxis\Core\Models\Locale::query()
                        ->where('is_active', true)
                        ->orderBy('sort_order')
                        ->get(['code', 'name', 'native_name', 'direction', 'is_default'])
                        ->toArray();
                } catch (\Exception $e) {
                    return [
                        [
                            'code' => app()->getLocale(),
                            'name' => 'Dari',
                            'native_name' => 'دری',
                            'direction' => 'rtl',
                            'is_default' => true,
                        ],
                    ];
                }
            },
            'csrf_token' => csrf_token(),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->is('delivery') || $request->is('delivery/*')
                    ? $request->user('delivery')
                    : $request->user(),
            ],
            'adminNotifications' => function () use ($request, $adminNotificationService) {
                if ((!$request->is('admin/*') && !$request->is('admin')) || !$request->user('admin')) {
                    return [
                        'unread_count' => 0,
                    ];
                }

                try {
                    return [
                        'unread_count' => $adminNotificationService->unreadCountForAdmin((int) $request->user('admin')->id),
                    ];
                } catch (\Exception $e) {
                    return [
                        'unread_count' => 0,
                    ];
                }
            },
            'adminConfig' => function () use ($request, $settingService) {
                // Only load for admin routes
                if (!$request->is('admin/*') && !$request->is('admin')) {
                    return null;
                }
                
                try {
                    return [
                        'logo' => $settingService->get('admin_logo') ?? null,
                        'site_name' => $settingService->get('site_name') ?? config('app.name'),
                    ];
                } catch (\Exception $e) {
                    return null;
                }
            },
            'adminMaintenance' => function () use ($request) {
                if (!$request->is('admin/*') && !$request->is('admin')) {
                    return null;
                }

                try {
                    return [
                        'enabled' => (bool) SystemSetting::get('system.maintenance_enabled', false),
                        'title' => (string) SystemSetting::get('system.maintenance_title', "We'll be back soon!"),
                    ];
                } catch (\Exception $e) {
                    return [
                        'enabled' => false,
                        'title' => "We'll be back soon!",
                    ];
                }
            },
            'menu' => [
                'admin' => $adminMenu,
                'shop' => $shopMenu,
            ],
            'flash' => \Inertia\Inertia::always(function () use ($request) {
                return [
                    'success' => $request->session()->get('success'),
                    'error' => $request->session()->get('error'),
                    'warning' => $request->session()->get('warning'),
                    'info' => $request->session()->get('info'),
                    'redirect_url' => $request->session()->get('redirect_url'),
                    'payment_response' => $request->session()->get('payment_response'),
                ];
            }),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'ziggy' => fn () => [
                // `Route::current()` is null whenever a shared prop is resolved
                // without a matched route -- during exception rendering, and on
                // fallback routes. Calling originalParameters() on it there threw,
                // which failed the page with no `X-Inertia` header and put Inertia's
                // error box on the screen. There are simply no route parameters
                // to report in that case.
                ...(Route::current()?->originalParameters() ?? []),
                'location' => $request->url(),
            ],
            // Currency configuration (shared for admin and frontend)
            //
            // `currency` is the BASE currency (AFN) and is what every stored
            // amount is in. `currencies` is the two the store supports and is
            // what the picker offers. `displayCurrency` is which of the two this
            // shopper is currently looking at -- the same codes, picked out of
            // the same list, so the frontend never has to guess.
            'currency' => function () {
                try {
                    $currency = Currency::getDefault();
                    return $currency ? [
                        'code' => $currency->code,
                        'name' => $currency->name,
                        'symbol' => $currency->symbol,
                        'symbolPosition' => $currency->symbol_position,
                        'decimalPlaces' => $currency->displayDecimals(),
                    ] : null;
                } catch (\Exception $e) {
                    return null;
                }
            },
            'currencies' => function () {
                try {
                    return Currency::selectable()
                        ->map(fn ($currency) => $currency->toDisplayArray())
                        ->values()
                        ->all();
                } catch (\Exception $e) {
                    return [];
                }
            },
            'displayCurrency' => function () {
                try {
                    return DisplayCurrency::resolve()->toDisplayArray();
                } catch (\Exception $e) {
                    return null;
                }
            },
            // The "1 USD = ? AFN" rate, so the frontend can show the shopper
            // what they are being shown.
            'usdToAfn' => fn () => Currency::usdToAfn(),
            // The store's calendar decisions, so the Solar Hijri picker and
            // every displayed date agree with config/calendar.php rather than
            // each repeating the defaults and drifting apart.
            'calendar' => fn () => \Cartxis\Calendar\Support\CalendarConfig::shared(),
            // Note: Theme-specific data (theme, contactInfo, socialLinks) is shared
            // by ShareFrontendData middleware via the hook system. Each theme registers
            // only the shared props it needs through its hooks.php file.

        ]);
    }
}
