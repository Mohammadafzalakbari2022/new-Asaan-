<?php

namespace Cartxis\Referral\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Cartxis\Referral\Http\Requests\ReverseReferralCommissionRequest;
use Cartxis\Referral\Models\ReferralCommission;
use Cartxis\Referral\Services\ReferralCreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReferralCommissionController extends Controller
{
    public function __construct(protected ReferralCreditService $credit) {}

    /**
     * List every referral reward that has been granted.
     */
    public function index(Request $request): Response
    {
        $query = ReferralCommission::query()->with(['referrer', 'referral.referred', 'order']);

        if ($search = trim((string) $request->get('search'))) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('referrer', fn ($u) => $u->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"))
                    ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->get('status')) {
            if ($status === 'locked') {
                $query->whereNotNull('unlocks_at')->where('unlocks_at', '>', now());
            } elseif ($status === 'available') {
                $query->where(fn ($q) => $q->whereNull('unlocks_at')->orWhere('unlocks_at', '<=', now()));
            } else {
                $query->where('status', $status);
            }
        }

        if ($level = $request->get('level')) {
            $query->where('level', (int) $level);
        }

        $commissions = $query
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 20))
            ->withQueryString();

        $commissions->getCollection()->transform(fn (ReferralCommission $c) => [
            'id' => $c->id,
            'referrer_name' => $c->referrer?->name ?? 'Deleted account',
            'referrer_email' => $c->referrer?->email,
            'referred_name' => $c->referral?->referred?->name ?? 'Deleted account',
            'order_number' => $c->order?->order_number,
            'level' => $c->level,
            'amount' => (float) $c->amount,
            'reward_snapshot' => (float) $c->reward_snapshot,
            'share_snapshot' => (float) $c->share_snapshot,
            'status' => $c->status,
            'unlocks_at' => $c->unlocks_at?->toDateString(),
            'is_locked' => $c->isLocked(),
            'reversal_reason' => $c->reversal_reason,
            'created_at' => $c->created_at?->toDateString(),
        ]);

        return Inertia::render('Admin/Marketing/Referrals/Commissions', [
            'commissions' => $commissions,
            'filters' => $request->only(['search', 'status', 'level']),
        ]);
    }

    /**
     * Take a reward back, with a written reason kept in the ledger.
     */
    public function reverse(ReverseReferralCommissionRequest $request, ReferralCommission $commission): RedirectResponse
    {
        $reason = $request->validated()['reason'];

        $this->credit->reverseCommission($commission, $reason, $request->user()->id);

        return back()->with('success', 'Reward of '.number_format((float) $commission->amount, 2).' reversed.');
    }
}
