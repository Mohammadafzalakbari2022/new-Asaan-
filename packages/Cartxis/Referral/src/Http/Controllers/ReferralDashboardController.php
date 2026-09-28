<?php

namespace Cartxis\Referral\Http\Controllers;

use App\Http\Controllers\Controller;
use Cartxis\Core\Services\ThemeViewResolver;
use Cartxis\Referral\Models\Referral;
use Cartxis\Referral\Services\ReferralCodeService;
use Cartxis\Referral\Services\ReferralCreditService;
use Cartxis\Referral\Services\ReferralEarningService;
use Cartxis\Referral\Services\ReferralLinkService;
use Cartxis\Referral\Services\ReferralSettings;
use Cartxis\Referral\Support\Money;
use Illuminate\Http\Request;
use Inertia\Response;

class ReferralDashboardController extends Controller
{
    public function __construct(
        protected ReferralSettings $settings,
        protected ReferralCodeService $codes,
        protected ReferralCreditService $credit,
        protected ReferralEarningService $earning,
        protected ReferralLinkService $links,
        protected ThemeViewResolver $themes,
    ) {}

    /**
     * The customer's own referral page: their code, their credit, their invites.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $code = $this->codes->forUser($user);
        $shareLink = $this->codes->shareLinkFor($code);
        $referrer = $this->links->referrerFor($user->id)?->referrer;

        $balances = $this->credit->balancesFor($user);

        return $this->themes->inertia('Account/Referrals/Index', [
            'referral' => [
                'code' => $code->code,
                'share_link' => $shareLink,
                'share_text' => $this->shareText($code->code, $shareLink),
                'clicks' => $code->clicks,
                'status' => $code->status,
            ],
            'balances' => $balances,
            'programme' => [
                'reward_amount' => $this->settings->rewardAmount(),
                'threshold_amount' => $this->settings->thresholdAmount(),
                'lock_days' => $this->settings->lockDays(),
                'credit_max_percent_of_order' => $this->settings->creditMaxPercentOfOrder(),
                'spends_excluding_shipping' => true,
            ],
            'invited' => Referral::where('referrer_user_id', $user->id)
                ->with('referred')
                ->orderByDesc('created_at')
                ->limit(50)
                ->get()
                ->map(fn (Referral $referral) => [
                    'id' => $referral->id,
                    'name' => $this->maskName($referral->referred?->name),
                    'lifetime_spend' => $this->earning->lifetimeQualifyingSpend($referral->referred_user_id),
                    'rewarded' => $referral->hasBeenRewarded(),
                    'status' => $referral->status,
                    'joined_on' => $referral->created_at?->toDateString(),
                ]),
            'invited_count' => Referral::where('referrer_user_id', $user->id)->count(),
            'rewarded_count' => Referral::where('referrer_user_id', $user->id)
                ->whereNotNull('rewarded_at')
                ->count(),
            'referredBy' => $referrer
                ? ['name' => $referrer->name]
                : null,
        ]);
    }

    /**
     * Show a plain page for someone who has just arrived from a shared link.
     */
    public function landing(Request $request): Response
    {
        // The code can arrive in the path (/referral/ABC-12345) or in the query
        // string (?ref=ABC-12345), because both shapes are pasted into messages.
        $code = $request->route('code') ?: $request->query('ref');

        return $this->themes->inertia('Account/Referrals/Landing', [
            'code' => $code,
            'programme' => [
                'reward_amount' => $this->settings->rewardAmount(),
                'threshold_amount' => $this->settings->thresholdAmount(),
                'lock_days' => $this->settings->lockDays(),
            ],
        ]);
    }

    /**
     * Show only the first letter of a name, so the page does not become a list of
     * everybody's full name on a screen other people can see.
     */
    protected function maskName(?string $name): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return 'A customer';
        }

        $mask = fn (string $part) => mb_substr($part, 0, 1).str_repeat('*', max(2, mb_strlen($part) - 1));

        $parts = array_values(array_filter(preg_split('/\s+/u', $name) ?: []));

        if (count($parts) === 1) {
            return $mask($parts[0]);
        }

        return $mask($parts[0]).' '.$mask($parts[count($parts) - 1]);
    }

    /**
     * The message a customer would send in a message app, so sharing is one tap.
     *
     * Only the person who sends the link earns the reward, so the wording must not
     * promise the friend anything. Promising a reward that never arrives is the
     * fastest way to get a refund request and a bad review.
     */
    protected function shareText(string $code, string $link): string
    {
        return __('Join with my link and I earn :reward of store credit when you have spent :threshold. My code is :code. :link', [
            'link' => $link,
            'code' => $code,
            'threshold' => Money::inSentence($this->settings->thresholdAmount()),
            'reward' => Money::inSentence($this->settings->rewardAmount()),
        ]);
    }
}
