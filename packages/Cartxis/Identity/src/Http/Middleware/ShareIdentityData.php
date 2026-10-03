<?php

namespace Cartxis\Identity\Http\Middleware;

use Cartxis\Identity\Services\IdentitySettings;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tells every Inertia page whether identity checks are switched on, so the
 * account menu can hide the link instead of sending people to a page that is
 * switched off.
 *
 * The value is lazy, so an ordinary page request costs one settings read.
 */
class ShareIdentityData
{
    public function __construct(
        protected IdentitySettings $settings,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        Inertia::share([
            'identityEnabled' => fn () => $this->settings->isEnabled(),
        ]);

        return $next($request);
    }
}
