<?php

namespace Cartxis\Sales\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfDeliveryAuthenticated
{
    /**
     * If a valid delivery session already exists, keep the driver inside
     * their portal instead of bouncing them back to the login screen.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->guard('delivery')->check()) {
            $driver = auth()->guard('delivery')->user();

            $isDelivery = is_object($driver)
                && (property_exists($driver, 'role') || isset($driver->role))
                && (string) ($driver->role ?? '') === 'delivery';

            $isActive = is_object($driver)
                && (property_exists($driver, 'is_active') || isset($driver->is_active))
                && (bool) $driver->is_active;

            if ($isDelivery && $isActive) {
                return redirect()->route('delivery.dashboard');
            }

            auth()->guard('delivery')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return $next($request);
    }
}