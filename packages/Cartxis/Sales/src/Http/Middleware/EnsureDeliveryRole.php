<?php

namespace Cartxis\Sales\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDeliveryRole
{
    /**
     * Ensure the authenticated delivery session belongs to an active
     * delivery-staff account (role = 'delivery'); otherwise drop the
     * session and send the visitor back to the delivery login.
     *
     * This is the mirror of PreventUserAdminAccess for the admin guard:
     * admins and customers must never get inside the driver portal.
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

            if (!$isDelivery || !$isActive) {
                auth()->guard('delivery')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                $message = !$isDelivery
                    ? 'You do not have permission to access the delivery portal.'
                    : 'Your delivery account has been deactivated.';

                return redirect()->route('delivery.login')
                    ->with('error', $message);
            }
        }

        return $next($request);
    }
}