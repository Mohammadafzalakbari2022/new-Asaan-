<?php

namespace Cartxis\Identity\Events;

use App\Models\User;
use Cartxis\Identity\Models\IdentityVerification;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An account has cleared identity verification.
 *
 * Fired after the approval is committed, so a listener that throws cannot undo a
 * reviewer's decision. Anything that used to be withheld from unverified people
 * and should now be released listens for this: the referral programme pays the
 * rewards that verification unblocked.
 */
class IdentityVerified
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly IdentityVerification $verification,
    ) {}
}