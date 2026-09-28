<?php

namespace Cartxis\Referral\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Cartxis\Referral\Http\Requests\SaveReferralSettingsRequest;
use Cartxis\Referral\Services\ReferralSettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ReferralSettingController extends Controller
{
    public function __construct(protected ReferralSettings $settings) {}

    /**
     * Show the programme settings form.
     */
    public function edit(): Response
    {
        return Inertia::render('Admin/Marketing/Referrals/Settings', [
            'settings' => $this->settings->toArray(),
            'levelBreakdown' => $this->settings->levelBreakdown(),
        ]);
    }

    /**
     * Save the programme settings.
     */
    public function update(SaveReferralSettingsRequest $request): RedirectResponse
    {
        $this->settings->save($request->validated());

        return redirect()
            ->route('admin.marketing.referrals.settings.edit')
            ->with('success', 'Referral settings saved.');
    }
}
