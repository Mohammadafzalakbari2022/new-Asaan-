<?php

namespace Cartxis\Referral\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Cartxis\Referral\Models\Referral;
use Cartxis\Referral\Models\ReferralCommission;
use Cartxis\Referral\Models\ReferralLedgerEntry;
use Cartxis\Referral\Services\ReferralCodeService;
use Cartxis\Referral\Services\ReferralCreditService;
use Cartxis\Referral\Services\ReferralEarningService;
use Cartxis\Referral\Services\ReferralSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReferralPeopleController extends Controller
{
    public function __construct(
        protected ReferralCodeService $codes,
        protected ReferralCreditService $credit,
        protected ReferralEarningService $earning,
        protected ReferralSettings $settings,
    ) {}

    /**
     * List everyone taking part in the referral programme.
     */
    public function index(Request $request): Response
    {
        $query = User::query()
            ->whereHas('referralCode')
            ->with('referralCode')
            ->withCount('referralsMade');

        if ($search = trim((string) $request->get('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('referralCode', fn ($c) => $c->where('code', 'like', "%{$search}%"));
            });
        }

        if ($request->get('filter') === 'earned') {
            $query->whereHas('referralsMade', fn ($q) => $q->whereNotNull('rewarded_at'));
        } elseif ($request->get('filter') === 'pending') {
            $query->whereHas('referralsMade', fn ($q) => $q->whereNull('rewarded_at'));
        } elseif ($request->get('filter') === 'none') {
            $query->doesntHave('referralsMade');
        }

        $people = $query
            // Ordered by the account's own creation date rather than the code's.
            // with('referralCode') runs as a separate query, so referral_codes is
            // not in this SELECT and ordering by one of its columns is a SQL error
            // on every database. A code is minted the moment the account is
            // created, so the two dates are the same thing anyway.
            ->orderByDesc('users.created_at')
            ->paginate($request->get('per_page', 20))
            ->withQueryString();

        $people->getCollection()->transform(function (User $user) {
            $balances = $this->credit->balancesFor($user);

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'code' => $user->referralCode?->code,
                'code_status' => $user->referralCode?->status,
                'clicks' => $user->referralCode?->clicks ?? 0,
                'referred_count' => $user->referrals_made_count ?? 0,
                'rewarded_count' => Referral::where('referrer_user_id', $user->id)
                    ->whereNotNull('rewarded_at')
                    ->count(),
                'invited_by' => Referral::where('referred_user_id', $user->id)->value('referrer_user_id'),
                'available' => $balances['available'],
                'locked' => $balances['locked'],
                'earned' => $balances['earned'],
                'created_at' => $user->created_at?->toDateString(),
            ];
        });

        return Inertia::render('Admin/Marketing/Referrals/People', [
            'people' => $people,
            'filters' => $request->only(['search', 'filter']),
        ]);
    }

    /**
     * Show one person's whole referral history.
     */
    public function show(User $user): Response
    {
        $code = $this->codes->forUser($user);

        $made = Referral::where('referrer_user_id', $user->id)
            ->with('referred')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Referral $referral) {
                return [
                    'id' => $referral->id,
                    'referred_name' => $referral->referred?->name ?? 'Deleted account',
                    'referred_email' => $referral->referred?->email,
                    'level' => $referral->level,
                    'status' => $referral->status,
                    'lifetime_spend' => $this->earning->lifetimeQualifyingSpend($referral->referred_user_id),
                    'rewarded_at' => $referral->rewarded_at?->toDateString(),
                    'created_at' => $referral->created_at?->toDateString(),
                ];
            });

        $referrer = Referral::with('referrer')->where('referred_user_id', $user->id)->first()?->referrer;

        return Inertia::render('Admin/Marketing/Referrals/PersonShow', [
            // The panel for hand-issuing credit is hidden entirely when the
            // setting is off, rather than shown and then refused on submit.
            'allowManualCredit' => $this->settings->adminCreditAllowed(),
            'person' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'created_at' => $user->created_at?->toDateString(),
                'code' => $code->code,
                'share_link' => $this->codes->shareLinkFor($code),
                'clicks' => $code->clicks,
                'code_status' => $code->status,
            ],
            'balances' => $this->credit->balancesFor($user),
            'referredBy' => $referrer
                ? ['id' => $referrer->id, 'name' => $referrer->name, 'email' => $referrer->email]
                : null,
            'referralsMade' => $made,
            'commissions' => ReferralCommission::where('referrer_user_id', $user->id)
                ->with('order')
                ->orderByDesc('created_at')
                ->limit(50)
                ->get()
                ->map(fn (ReferralCommission $c) => [
                    'id' => $c->id,
                    'order_number' => $c->order?->order_number,
                    'level' => $c->level,
                    'amount' => (float) $c->amount,
                    'status' => $c->status,
                    'unlocks_at' => $c->unlocks_at?->toDateString(),
                    'created_at' => $c->created_at?->toDateString(),
                ]),
            'ledger' => ReferralLedgerEntry::where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->limit(50)
                ->get()
                ->map(fn (ReferralLedgerEntry $e) => [
                    'id' => $e->id,
                    'type' => $e->type,
                    'amount' => (float) $e->amount,
                    'balance_after' => (float) $e->balance_after,
                    'reason' => $e->reason,
                    'created_at' => $e->created_at?->toDateString(),
                ]),
        ]);
    }
}
