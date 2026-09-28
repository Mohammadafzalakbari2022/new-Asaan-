<?php

namespace Cartxis\Referral\Services;

use App\Models\User;
use Cartxis\Referral\Models\ReferralCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Mints, looks up and shares referral codes.
 */
class ReferralCodeService
{
    /**
     * No 0/O/1/I/L, because customers read these aloud and type them from memory.
     */
    protected const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    protected const SUFFIX_LENGTH = 5;

    public function __construct(protected ReferralSettings $settings) {}

    /**
     * Get the code for a user, minting one on first use.
     */
    public function forUser(User $user): ReferralCode
    {
        $existing = ReferralCode::where('user_id', $user->id)->first();

        if ($existing) {
            return $existing;
        }

        return $this->mint($user);
    }

    public function mint(User $user): ReferralCode
    {
        return DB::transaction(function () use ($user) {
            // A code already exists if another request won the race.
            $existing = ReferralCode::where('user_id', $user->id)->lockForUpdate()->first();

            if ($existing) {
                return $existing;
            }

            return ReferralCode::create([
                'user_id' => $user->id,
                'code' => $this->generateUniqueCode($user),
                'status' => ReferralCode::STATUS_ACTIVE,
                'clicks' => 0,
            ]);
        });
    }

    /**
     * Find an active code by its value, case-insensitive.
     */
    public function findByCode(string $code): ?ReferralCode
    {
        $code = $this->normalise($code);

        if ($code === '') {
            return null;
        }

        return ReferralCode::active()
            ->whereRaw('LOWER(code) = ?', [mb_strtolower($code)])
            ->first();
    }

    /**
     * The shareable link for a code.
     */
    public function shareLinkFor(ReferralCode $code): string
    {
        $base = rtrim((string) config('app.url'), '/');

        if ($base === '' || $base === 'http://localhost') {
            $base = rtrim((string) config('app.url'), '/');
        }

        return $base.'/?ref='.rawurlencode($code->code);
    }

    public function shareLinkForUser(User $user): string
    {
        return $this->shareLinkFor($this->forUser($user));
    }

    public function recordClick(ReferralCode $code): void
    {
        $code->recordClick();
    }

    public function normalise(string $code): string
    {
        return Str::upper(trim($code));
    }

    /**
     * A readable code like AHMAD-7K2QX, retried on the (vanishingly rare) collision.
     */
    public function generateUniqueCode(User $user): string
    {
        $prefix = $this->prefixFor($user);

        for ($attempt = 0; $attempt < 12; $attempt++) {
            $code = $prefix.'-'.$this->randomSuffix();

            if (! ReferralCode::whereRaw('LOWER(code) = ?', [mb_strtolower($code)])->exists()) {
                return $code;
            }
        }

        // Fall back to something that cannot collide.
        return 'REF-'.strtoupper(Str::random(12));
    }

    protected function prefixFor(User $user): string
    {
        $name = (string) $user->name;

        $letters = Str::of($name)
            ->ascii()
            ->upper()
            ->replaceMatches('/[^A-Z]/', '')
            ->limit(6, '')
            ->toString();

        if ($letters === '') {
            $letters = 'REF';
        }

        return $letters;
    }

    protected function randomSuffix(): string
    {
        $alphabet = self::ALPHABET;
        $max = strlen($alphabet) - 1;
        $out = '';

        for ($i = 0; $i < self::SUFFIX_LENGTH; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }

        return $out;
    }
}
