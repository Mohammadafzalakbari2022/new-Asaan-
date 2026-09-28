<?php

namespace Cartxis\Referral\Http\Middleware;

use Cartxis\Referral\Services\ReferralSettings;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tells every Inertia page whether the programme is switched on, so the header
 * can hide the "Refer & Earn" link instead of taking people to a page that is
 * switched off.
 *
 * The value is lazy, so a normal page request costs one settings read, and an
 * admin request costs nothing at all.
 */
class ShareReferralData
{
    public function __construct(protected ReferralSettings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        Inertia::share([
            'referralEnabled' => fn () => $this->settings->isEnabled(),
        ]);

        return $next($request);
    }
}
