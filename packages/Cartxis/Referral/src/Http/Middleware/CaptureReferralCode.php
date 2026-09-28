<?php

namespace Cartxis\Referral\Http\Middleware;

use Cartxis\Referral\Services\ReferralCodeService;
use Cartxis\Referral\Services\ReferralLinkService;
use Cartxis\Referral\Services\ReferralSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks up ?ref=CODE from any page and remembers it for 30 days, so a customer
 * who clicks a referral link, browses for a while and only then signs up is
 * still credited to whoever sent them.
 */
class CaptureReferralCode
{
    public function __construct(
        protected ReferralSettings $settings,
        protected ReferralCodeService $codes,
        protected ReferralLinkService $links,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $code = $request->query('ref');

        if (! is_string($code) || trim($code) === '') {
            return $next($request);
        }

        if (! $this->settings->isEnabled()) {
            return $next($request);
        }

        $normalised = $this->codes->normalise($code);
        $referralCode = $this->codes->findByCode($normalised);

        if (! $referralCode) {
            return $next($request);
        }

        // Never let a customer credit themselves.
        if ($request->user() && $request->user()->id === $referralCode->user_id) {
            return $next($request);
        }

        $this->codes->recordClick($referralCode);
        $this->links->rememberPendingCode($normalised);
        $request->session()->put('referral_referrer_name', $referralCode->user?->name);

        return $next($request);
    }
}
