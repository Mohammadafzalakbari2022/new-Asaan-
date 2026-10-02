<?php

namespace Cartxis\Identity\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Is this account identity-verified?
 *
 * This is the chokepoint the referral programme asks. It is a separate service
 * rather than a static helper so that the package has exactly one definition of
 * "verified", and so the referral package has one thing to depend on.
 *
 * Failing behaviour is deliberate:
 *
 *  - the columns are missing (an install mid-migration): NOT verified. A gate
 *    that opens itself because a table is absent is not a gate.
 *  - the package is not installed at all: verified, because then the store never
 *    asked for identity checks and must not silently stop paying referral
 *    rewards. Only the class being absent can produce that answer, never a
 *    missing row.
 */
class IdentityStatus
{
    protected ?bool $tableExists = null;

    public function __construct(protected IdentitySettings $settings) {}

    public function isVerified(User|int|null $user): bool
    {
        $userId = $user instanceof User ? $user->id : $user;

        if (! $userId) {
            return false;
        }

        if (! $this->accountsCanBeVerified()) {
            return false;
        }

        return DB::table('users')
            ->where('id', $userId)
            ->whereNotNull('identity_verified_at')
            ->exists();
    }

    /**
     * When the account was verified, or null.
     */
    public function verifiedAt(User|int|null $user): ?string
    {
        $userId = $user instanceof User ? $user->id : $user;

        if (! $userId || ! $this->accountsCanBeVerified()) {
            return null;
        }

        $value = DB::table('users')->where('id', $userId)->value('identity_verified_at');

        return $value === null ? null : (string) $value;
    }

    /**
     * True when identity verification is switched on for referrals at all. When
     * the owner has turned it off, nobody is treated as verified for the purpose
     * of the gate, because the gate is not in play.
     */
    public function gateApplies(): bool
    {
        return $this->settings->isEnabled()
            && $this->settings->requiresVerificationForReferral();
    }

    /**
     * Cache the table lookup for the length of the request. This sits on the
     * path of every paid order, and a schema query per level per order is not
     * free.
     */
    protected function accountsCanBeVerified(): bool
    {
        if ($this->tableExists === null) {
            try {
                $this->tableExists = Schema::hasTable('users')
                    && Schema::hasColumn('users', 'identity_verified_at');
            } catch (\Throwable) {
                $this->tableExists = false;
            }
        }

        return $this->tableExists;
    }
}