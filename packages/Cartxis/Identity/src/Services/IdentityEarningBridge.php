<?php

namespace Cartxis\Identity\Services;

use App\Models\User;
use Cartxis\Identity\Events\IdentityVerified;
use Cartxis\Referral\Services\ReferralEarningService;
use Illuminate\Support\Facades\Log;

/**
 * Tells the rest of the shop that somebody has just been verified.
 *
 * The referral programme is the one part of the shop that is held back by
 * verification, so it is called directly rather than waiting to be wired up:
 * a reviewer pressing Approve must not have to also know that some other package
 * needs to be told.
 *
 * The class check is what keeps the packages independent. Identity ships and
 * works with or without the referral package installed; a store that removed the
 * referral programme still gets identity verification.
 */
class IdentityEarningBridge
{
    /**
     * Called after an approval is committed. Never throws.
     */
    public function afterVerification(User $user, \Cartxis\Identity\Models\IdentityVerification $verification): void
    {
        $this->payUnblockedReferralRewards($user);

        try {
            event(new IdentityVerified($user, $verification));
        } catch (\Throwable $e) {
            // A listener that fails must not turn a completed review into an
            // error the reviewer sees.
            Log::error('Identity verified listener failed', [
                'user_id' => $user->id,
                'verification_id' => $verification->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Retroactive payout: orders that crossed the referral threshold while this
     * person was unverified are paid now, and only once.
     */
    protected function payUnblockedReferralRewards(User $user): void
    {
        if (! class_exists(ReferralEarningService::class)) {
            return;
        }

        try {
            app(ReferralEarningService::class)->onIdentityVerified($user->id);
        } catch (\Throwable $e) {
            // Money owed to a referrer must never be lost because one referrer's
            // own payout had a problem. The award itself is idempotent, so this
            // can be retried safely.
            Log::error('Referral back-payment after identity verification failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}