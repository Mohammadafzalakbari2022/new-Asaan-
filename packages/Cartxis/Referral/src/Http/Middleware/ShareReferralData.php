<?php

namespace Cartxis\Referral\Http\Middleware;

use Cartxis\Referral\Services\ReferralLinkService;
use Cartxis\Referral\Services\ReferralSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tells every Inertia page whether the programme is switched on, so the header
 * can hide the "Refer & Earn" link instead of taking people to a page that is
 * switched off.
 *
 * It also hands over the name of whoever invited this visitor, so the
 * registration page can say "Invited by Ahmad" instead of letting somebody
 * arrive from a link and never find out who sent them.
 *
 * The values are lazy, so a normal page request costs one settings read, and an
 * admin request costs nothing at all.
 */
class ShareReferralData
{
    /**
     * Long enough for a real name, short enough that a tampered session cannot
     * push a screenful of text into the page.
     */
    protected const MAX_NAME_LENGTH = 120;

    public function __construct(
        protected ReferralSettings $settings,
        protected ReferralLinkService $links,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        Inertia::share([
            'referralEnabled' => fn () => $this->settings->isEnabled(),
            'referralReferrerName' => fn () => $this->referrerName(),
        ]);

        return $next($request);
    }

    /**
     * The inviter's display name, or null when nobody invited this visitor.
     *
     * The name is only meaningful while there is still a code that will be
     * applied at signup, so an expired or already-used code shows nothing
     * rather than a name that is no longer true.
     */
    protected function referrerName(): ?string
    {
        if ($this->links->pendingCode() === null) {
            return null;
        }

        $name = trim((string) session('referral_referrer_name', ''));

        if ($name === '') {
            return null;
        }

        return Str::limit($name, self::MAX_NAME_LENGTH);
    }
}
