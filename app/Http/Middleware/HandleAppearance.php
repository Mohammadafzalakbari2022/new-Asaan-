<?php

namespace App\Http\Middleware;

use Cartxis\Core\Services\SettingService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    public function __construct(private SettingService $settingService) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $appearance = $request->cookie('appearance') ?? 'system';
        $forceLightStorefront = false;

        if (! $request->is('admin') && ! $request->is('admin/*')) {
            try {
                $theme = \Cartxis\Core\Models\Theme::active();
                $config = $theme?->getConfig() ?? [];

                // The storefront themes shipped in this build are light-only:
                // every surface is a hard-coded bg-white / bg-slate-50 with
                // gray-700 text, and none use dark: variants. If the visitor's
                // system is in dark mode (or the admin appearance cookie says
                // "dark"), the app's dark palette turns body text white, which
                // is invisible on those light surfaces — the whole storefront
                // ends up white-on-white. Only a theme that explicitly opts in
                // via "dark_mode": true in its theme.json and ships its own
                // dark styles keeps the user's light/dark preference.
                if (empty($config['dark_mode'])) {
                    $appearance = 'light';
                    $forceLightStorefront = true;
                }
            } catch (\Throwable $e) {
                // ignore during install/migrations
            }
        }

        View::share('appearance', $appearance);
        View::share('forceLightStorefront', $forceLightStorefront);

        $storedFavicon = $this->settingService->get('site_favicon');
        $faviconUrl = $storedFavicon
            ? Storage::disk('public')->url($storedFavicon)
            : config('branding.favicon_asset', '/logos/asaan-favicon.png');

        View::share('favicon', $faviconUrl);

        return $next($request);
    }
}
