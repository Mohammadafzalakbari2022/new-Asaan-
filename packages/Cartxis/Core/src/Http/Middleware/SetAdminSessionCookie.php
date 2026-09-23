<?php

namespace Cartxis\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetAdminSessionCookie
{
    /**
     * Override the session cookie name before StartSession reads it,
     * giving the admin panel and the delivery-staff portal their own
     * independent sessions so they can coexist with a storefront user
     * session in the same browser.
     *
     * Registered as a global prepend middleware by CoreServiceProvider
     * so it covers ALL packages' admin routes automatically.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('admin') || $request->is('admin/*')) {
            config(['session.cookie' => config('session.cookie') . '_admin']);
        } elseif ($request->is('delivery') || $request->is('delivery/*')) {
            config(['session.cookie' => config('session.cookie') . '_delivery']);
        }

        return $next($request);
    }
}
