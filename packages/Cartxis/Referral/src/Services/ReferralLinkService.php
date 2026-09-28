<?php

namespace Cartxis\Referral\Services;

use App\Models\User;
use Cartxis\Referral\Models\Referral;
use Cartxis\Referral\Models\ReferralCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Creates the link between a customer and whoever referred them.
 */
class ReferralLinkService
{
    /**
     * Why a link was refused. Kept small and stable so callers and tests can rely on it.
     */
    public const REASON_PROGRAMME_OFF = 'programme_off';

    public const REASON_NO_CODE = 'no_code';

    public const REASON_INVALID_CODE = 'invalid_code';

    public const REASON_SELF = 'self_referral';

    public const REASON_SAME_EMAIL = 'same_email';

    public const REASON_SAME_PHONE = 'same_phone';

    public const REASON_ALREADY_LINKED = 'already_linked';

    public const REASON_TOO_DEEP = 'too_deep';

    public function __construct(
        protected ReferralSettings $settings,
        protected ReferralCodeService $codes,
    ) {}

    /**
     * Link a freshly created user to the referrer holding $code.
     *
     * Never throws. A failed referral must not be able to break signup, so every
     * refusal is returned as a reason and logged instead.
     */
    public function linkFor(User $user, ?string $code = null): ?Referral
    {
        $code = $code !== null ? $code : $this->pendingCode();

        if (! $this->settings->isEnabled()) {
            return null;
        }

        if ($code === null || trim($code) === '') {
            return null;
        }

        $referralCode = $this->codes->findByCode($code);

        if (! $referralCode) {
            return null;
        }

        $result = $this->linkToCode($user, $referralCode);

        // Log only genuine refusals. Without this, a store owner whose
        // invitations are being silently dropped has no way to find out why.
        // A null referral with no reason is the "nothing to do" case, not a
        // refusal, so it is not logged.
        // Log only genuine refusals. Without this, a store owner whose
        // invitations are being silently dropped has no way to find out why.
        // A null referral with no reason is the "nothing to do" case, not a
        // refusal, so it is not logged.
        if ($result['referral'] === null && $result['reason'] !== null) {
            Log::info('Referral link refused', [
                'user_id' => $user->id,
                'referrer_id' => $referralCode->user_id,
                'code' => $referralCode->code,
                'reason' => $result['reason'],
            ]);
        }

        return $result['referral'];
    }

    /**
     * @return array{referral: ?Referral, reason: ?string}
     */
    public function linkToCode(User $user, ReferralCode $referralCode): array
    {
        $referrerId = $referralCode->user_id;

        if ($referrerId === $user->id) {
            return ['referral' => null, 'reason' => self::REASON_SELF];
        }

        if (Referral::where('referred_user_id', $user->id)->exists()) {
            return ['referral' => null, 'reason' => self::REASON_ALREADY_LINKED];
        }

        if ($this->sharesIdentity($user, $referrerId)) {
            return ['referral' => null, 'reason' => $this->identityReason($user, $referrerId)];
        }

        $referrerOwnReferral = Referral::where('referred_user_id', $referrerId)->first();
        $level = $referrerOwnReferral ? 2 : 1;

        // Nobody joins the tree if their referrer's referrer would get nothing.
        if ($level > $this->settings->maxLevels()) {
            return ['referral' => null, 'reason' => self::REASON_TOO_DEEP];
        }

        try {
            $referral = DB::transaction(function () use ($user, $referrerId, $referralCode, $level) {
                // Re-check inside the transaction so two concurrent signup requests
                // cannot both create a row for the same person.
                if (Referral::where('referred_user_id', $user->id)->exists()) {
                    return null;
                }

                return Referral::create([
                    'referrer_user_id' => $referrerId,
                    'referred_user_id' => $user->id,
                    'referral_code_id' => $referralCode->id,
                    'level' => $level,
                    'status' => Referral::STATUS_ACTIVE,
                ]);
            });
        } catch (\Throwable $e) {
            Log::warning('Referral link could not be created', [
                'user_id' => $user->id,
                'referrer_id' => $referrerId,
                'error' => $e->getMessage(),
            ]);

            return ['referral' => null, 'reason' => self::REASON_ALREADY_LINKED];
        }

        if ($referral) {
            // The referral code has done its job; clear it so a later, unrelated
            // signup on the same browser is not silently attributed.
            $this->forgetPendingCode();
        }

        return ['referral' => $referral, 'reason' => $referral ? null : self::REASON_ALREADY_LINKED];
    }

    public function referrerFor(int $referredUserId): ?Referral
    {
        return Referral::where('referred_user_id', $referredUserId)->first();
    }

    /**
     * The upline chain for a user, nearest ancestor first, capped at max levels.
     *
     * @return array<int, int> user ids
     */
    public function uplineFor(int $userId, ?int $limit = null): array
    {
        $limit ??= $this->settings->maxLevels();
        $chain = [];
        $seen = [$userId => true];
        $current = $userId;

        for ($depth = 0; $depth < $limit; $depth++) {
            $referral = Referral::where('referred_user_id', $current)->first();

            if (! $referral || isset($seen[$referral->referrer_user_id])) {
                break;
            }

            $chain[] = $referral->referrer_user_id;
            $seen[$referral->referrer_user_id] = true;
            $current = $referral->referrer_user_id;
        }

        return $chain;
    }

    public function pendingCode(): ?string
    {
        if (! session()->has('referral_code')) {
            return null;
        }

        $payload = session('referral_code');

        if (! is_array($payload) || ! isset($payload['code'])) {
            return null;
        }

        if (isset($payload['expires_at']) && now()->greaterThan($payload['expires_at'])) {
            $this->forgetPendingCode();

            return null;
        }

        return (string) $payload['code'];
    }

    public function rememberPendingCode(string $code): void
    {
        session()->put('referral_code', [
            'code' => $this->codes->normalise($code),
            'expires_at' => now()->addDays(30),
        ]);
    }

    public function forgetPendingCode(): void
    {
        session()->forget('referral_code');
    }

    /**
     * A referral may not be a person signing themselves up, nor a second account
     * belonging to the same person.
     */
    protected function sharesIdentity(User $user, int $otherUserId): bool
    {
        return $this->identityReason($user, $otherUserId) !== null;
    }

    protected function identityReason(User $user, int $otherUserId): ?string
    {
        $other = User::where('id', $otherUserId)->first();

        if (! $other) {
            return self::REASON_INVALID_CODE;
        }

        if (
            $user->email
            && $other->email
            && mb_strtolower($user->email) === mb_strtolower($other->email)
        ) {
            return self::REASON_SAME_EMAIL;
        }

        if ($user->phone && $other->phone && $user->phone === $other->phone) {
            return self::REASON_SAME_PHONE;
        }

        return null;
    }
}
