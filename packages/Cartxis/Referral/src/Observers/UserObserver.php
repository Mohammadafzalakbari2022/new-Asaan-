<?php

namespace Cartxis\Referral\Observers;

use App\Models\User;
use Cartxis\Referral\Services\ReferralCodeService;
use Cartxis\Referral\Services\ReferralLinkService;
use Cartxis\Referral\Services\ReferralSettings;
use Illuminate\Support\Facades\Log;

/**
 * Mints a referral code for every account, and links it to whoever sent them.
 *
 * Done on the model rather than in each controller, because accounts are created
 * in four places today (register page, checkout, admin customer create, API
 * register) and a fifth will be added later.
 */
class UserObserver
{
    public function __construct(
        protected ReferralSettings $settings,
        protected ReferralCodeService $codes,
        protected ReferralLinkService $links,
    ) {}

    public function created(User $user): void
    {
        if (! $this->settings->isEnabled()) {
            return;
        }

        try {
            $this->codes->mint($user);
        } catch (\Throwable $e) {
            Log::warning('Referral code could not be minted', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $this->links->linkFor($user);
        } catch (\Throwable $e) {
            Log::warning('Referral link could not be created on signup', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
