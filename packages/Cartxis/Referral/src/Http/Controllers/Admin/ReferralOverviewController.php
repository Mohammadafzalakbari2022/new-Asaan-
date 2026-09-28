<?php

namespace Cartxis\Referral\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Cartxis\Referral\Services\ReferralSettings;
use Cartxis\Referral\Services\ReferralStatsService;
use Inertia\Inertia;
use Inertia\Response;

class ReferralOverviewController extends Controller
{
    public function __construct(
        protected ReferralStatsService $stats,
        protected ReferralSettings $settings,
    ) {}

    /**
     * Display the referral programme overview.
     */
    public function index(): Response
    {
        return Inertia::render('Admin/Marketing/Referrals/Index', [
            'stats' => $this->stats->overview(),
            'trend' => $this->stats->dailyTrend(30),
            'topReferrers' => $this->stats->topReferrers(10),
        ]);
    }

    /**
     * Display the full ranked list of earners.
     */
    public function topReferrers(): Response
    {
        return Inertia::render('Admin/Marketing/Referrals/TopReferrers', [
            'topReferrers' => $this->stats->topReferrers(100),
            'settings' => $this->settings->toArray(),
        ]);
    }
}
