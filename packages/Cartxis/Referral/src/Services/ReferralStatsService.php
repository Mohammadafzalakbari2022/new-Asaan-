<?php

namespace Cartxis\Referral\Services;

use App\Models\User;
use Cartxis\Referral\Models\Referral;
use Cartxis\Referral\Models\ReferralCommission;
use Cartxis\Referral\Models\ReferralLedgerEntry;
use Cartxis\Shop\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Numbers for the admin overview screen.
 */
class ReferralStatsService
{
    public function __construct(
        protected ReferralSettings $settings,
        protected ReferralCreditService $credit,
    ) {}

    public function overview(): array
    {
        $totalReferrals = Referral::where('status', Referral::STATUS_ACTIVE)->count();
        $rewarded = Referral::whereNotNull('rewarded_at')->count();

        $activeCommissions = ReferralCommission::where('status', ReferralCommission::STATUS_ACTIVE);

        $liabilities = (float) (clone $activeCommissions)->sum('amount');
        $lockedLiabilities = (float) (clone $activeCommissions)
            ->whereNotNull('unlocks_at')
            ->where('unlocks_at', '>', now())
            ->sum('amount');

        $spent = abs((float) ReferralLedgerEntry::where('type', ReferralLedgerEntry::TYPE_SPENT)->sum('amount'));
        $outstanding = round($liabilities - $spent, 2);

        $withCodes = DB::table('referral_codes')->count();
        $programmeUsers = DB::table('referral_codes')
            ->join('users', 'users.id', '=', 'referral_codes.user_id')
            ->where('users.role', 'customer')
            ->count();

        return [
            'enabled' => $this->settings->isEnabled(),
            'reward_amount' => $this->settings->rewardAmount(),
            'threshold_amount' => $this->settings->thresholdAmount(),
            'lock_days' => $this->settings->lockDays(),
            'level_breakdown' => $this->settings->levelBreakdown(),
            'codes_issued' => $withCodes,
            'programme_members' => $programmeUsers,
            'total_referrals' => $totalReferrals,
            'referrals_rewarded' => $rewarded,
            'referrals_pending' => max(0, $totalReferrals - $rewarded),
            'conversion_rate' => $totalReferrals > 0
                ? round(($rewarded / $totalReferrals) * 100, 1)
                : 0.0,
            'total_credit_generated' => round($liabilities, 2),
            'locked_credit' => round($lockedLiabilities, 2),
            'credit_spent' => round($spent, 2),
            'outstanding_liability' => max(0, $outstanding),
            'avg_lifetime_spend_of_referred' => $rewarded > 0
                ? round($this->averageLifetimeSpend(), 2)
                : 0.0,
        ];
    }

    /**
     * Ranked earners, straight from the commissions table.
     */
    public function topReferrers(int $limit = 25)
    {
        return ReferralCommission::query()
            ->selectRaw('referrer_user_id, SUM(amount) as total_earned, COUNT(*) as awards, MAX(created_at) as last_award_at')
            ->where('status', ReferralCommission::STATUS_ACTIVE)
            ->groupBy('referrer_user_id')
            ->orderByDesc('total_earned')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $user = User::where('id', $row->referrer_user_id)->first();

                return [
                    'user_id' => $row->referrer_user_id,
                    'name' => $user?->name ?? 'Deleted account',
                    'email' => $user?->email,
                    'total_earned' => round((float) $row->total_earned, 2),
                    'awards' => (int) $row->awards,
                    'last_award_at' => $row->last_award_at,
                    'available' => $user ? $this->credit->availableBalance($user->id) : 0.0,
                    'locked' => $user ? $this->credit->lockedBalance($user->id) : 0.0,
                ];
            });
    }

    /**
     * Commission totals for the last $days days, oldest first, for the trend chart.
     */
    public function dailyTrend(int $days = 30): array
    {
        $rows = ReferralCommission::query()
            ->selectRaw('DATE(created_at) as day, SUM(amount) as total, COUNT(*) as awards')
            ->where('created_at', '>=', now()->subDays($days)->startOfDay())
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day');

        $series = [];
        $start = now()->subDays($days - 1)->startOfDay();

        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i)->toDateString();
            $series[] = [
                'day' => $day,
                'total' => round((float) ($rows[$day] ?? 0), 2),
            ];
        }

        return $series;
    }

    public function referralsCount(): int
    {
        return Referral::where('status', Referral::STATUS_ACTIVE)->count();
    }

    protected function averageLifetimeSpend(): float
    {
        $total = 0.0;
        $counted = 0;

        $referrals = Referral::whereNotNull('rewarded_at')->limit(500)->get(['referred_user_id']);

        foreach ($referrals as $referral) {
            $total += $this->lifetimeSpendFor($referral->referred_user_id);
            $counted++;
        }

        return $counted > 0 ? $total / $counted : 0.0;
    }

    protected function lifetimeSpendFor(int $userId): float
    {
        $customerIds = DB::table('customers')->where('user_id', $userId)->pluck('id')->all();

        return round((float) Order::query()
            ->where('payment_status', Order::PAYMENT_PAID)
            ->whereNotIn('status', [Order::STATUS_CANCELLED, Order::STATUS_REFUNDED])
            ->where(function ($q) use ($userId, $customerIds) {
                $q->where('user_id', $userId);

                if ($customerIds !== []) {
                    $q->orWhereIn('customer_id', $customerIds);
                }
            })
            ->sum('total'), 2);
    }
}
