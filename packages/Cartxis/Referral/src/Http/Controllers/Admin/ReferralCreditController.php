<?php

namespace Cartxis\Referral\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Cartxis\Referral\Http\Requests\StoreReferralCreditRequest;
use Cartxis\Referral\Services\ReferralCreditService;
use Cartxis\Referral\Services\ReferralSettings;
use Illuminate\Http\RedirectResponse;

class ReferralCreditController extends Controller
{
    public function __construct(
        protected ReferralCreditService $credit,
        protected ReferralSettings $settings,
    ) {}

    /**
     * Give credit by hand, or take it back by hand. Always with a written reason.
     */
    public function store(StoreReferralCreditRequest $request): RedirectResponse
    {
        if (! $this->settings->adminCreditAllowed()) {
            return back()->with('error', 'Hand-issued referral credit is switched off in settings.');
        }

        $validated = $request->validated();
        $userId = (int) $validated['user_id'];
        $amount = (float) $validated['amount'];
        $reason = $validated['reason'];
        $adminId = (int) $request->user()->id;

        if ($validated['type'] === 'admin_credit') {
            $this->credit->adminCredit($userId, $amount, $adminId, $reason);

            return back()->with('success', number_format($amount, 2).' referral credit added.');
        }

        $available = $this->credit->availableBalance($userId);

        if ($amount > $available) {
            return back()->with(
                'error',
                'That customer only has '.number_format($available, 2).' of credit available to take back.'
            );
        }

        $this->credit->adminDebit($userId, $amount, $adminId, $reason);

        return back()->with('success', number_format($amount, 2).' referral credit taken back.');
    }
}
