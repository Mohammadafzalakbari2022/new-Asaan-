<?php

namespace Cartxis\Identity\Services;

use Cartxis\Core\Services\SettingService;

/**
 * Typed access to the identity settings.
 *
 * Every value has a baked-in default, taken from the package config rather than
 * from the settings table, so the referral gate is closed even on an install
 * whose settings rows were never written. A missing row must never mean "the
 * gate is open".
 */
class IdentitySettings
{
    public const GROUP = 'identity';

    public const ENABLED = 'identity.enabled';

    public const REQUIRE_REFERRAL_VERIFICATION = 'identity.require_verification_for_referral';

    public const RETENTION_DAYS = 'identity.retention_days';

    public const FACE_MATCH_ENABLED = 'identity.face_match_enabled';

    public function __construct(
        protected SettingService $settings,
        protected IdentityConfig $config,
    ) {}

    /**
     * May customers submit a document at all?
     */
    public function isEnabled(): bool
    {
        return $this->bool(self::ENABLED, true);
    }

    /**
     * Must an account be identity-verified before a referral reward can be paid?
     *
     * Defaults to true. The owner can open this deliberately, and the admin log
     * records it when they do, but it is never the state a fresh install starts
     * in by accident.
     */
    public function requiresVerificationForReferral(): bool
    {
        return $this->bool(self::REQUIRE_REFERRAL_VERIFICATION, true);
    }

    /**
     * Days a rejected or superseded image is kept before it is deleted.
     */
    public function retentionDays(): int
    {
        return max(0, (int) $this->raw(
            self::RETENTION_DAYS,
            $this->config->get('retention_days', 30)
        ));
    }

    /**
     * Reserved. Always false in this release.
     */
    public function faceMatchEnabled(): bool
    {
        return false;
    }

    public function toArray(): array
    {
        return [
            'enabled' => $this->isEnabled(),
            'require_verification_for_referral' => $this->requiresVerificationForReferral(),
            'retention_days' => $this->retentionDays(),
            'face_match_enabled' => $this->faceMatchEnabled(),
        ];
    }

    protected function bool(string $key, bool $default): bool
    {
        $value = $this->raw($key, $default);

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'on', 'yes'], true);
        }

        return (bool) $value;
    }

    protected function raw(string $key, mixed $default): mixed
    {
        try {
            $value = $this->settings->get($key, $default);
        } catch (\Throwable) {
            // The settings table may not exist yet during an install. The
            // packaged default is the safe answer in every case.
            return $default;
        }

        return $value ?? $default;
    }
}