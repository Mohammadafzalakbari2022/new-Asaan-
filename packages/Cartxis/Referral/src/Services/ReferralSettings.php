<?php

namespace Cartxis\Referral\Services;

use Cartxis\Core\Services\SettingService;

/**
 * Typed access to the referral programme settings.
 *
 * Every value has a baked-in default so the programme behaves correctly even
 * before the settings migration has run, and can never crash the app because a
 * row was deleted by hand.
 */
class ReferralSettings
{
    public const GROUP = 'referral';

    public const ENABLED = 'referral.enabled';

    public const REWARD_AMOUNT = 'referral.reward_amount';

    public const THRESHOLD_AMOUNT = 'referral.threshold_amount';

    public const LOCK_DAYS = 'referral.lock_days';

    public const LEVEL_SHARES = 'referral.level_shares';

    public const REWARD_MODE = 'referral.reward_mode';

    public const ALLOW_ADMIN_CREDIT = 'referral.allow_admin_credit';

    public const BLOCK_ACCOUNT_DELETION = 'referral.block_account_deletion';

    public const CREDIT_MAX_PERCENT_OF_ORDER = 'referral.credit_max_percent_of_order';

    public const REWARD_MODE_ONCE_PER_PERSON = 'once_per_person';

    /**
     * Defaults, mirrored by the settings seed migration.
     */
    public const DEFAULTS = [
        self::ENABLED => true,
        self::REWARD_AMOUNT => 10.0,
        self::THRESHOLD_AMOUNT => 500.0,
        self::LOCK_DAYS => 180,
        self::LEVEL_SHARES => [100],
        self::REWARD_MODE => self::REWARD_MODE_ONCE_PER_PERSON,
        self::ALLOW_ADMIN_CREDIT => true,
        self::BLOCK_ACCOUNT_DELETION => true,
        self::CREDIT_MAX_PERCENT_OF_ORDER => 100,
    ];

    public function __construct(protected SettingService $settings) {}

    public function isEnabled(): bool
    {
        return $this->bool(self::ENABLED);
    }

    public function rewardAmount(): float
    {
        return $this->float(self::REWARD_AMOUNT);
    }

    public function thresholdAmount(): float
    {
        return $this->float(self::THRESHOLD_AMOUNT);
    }

    public function lockDays(): int
    {
        return (int) $this->raw(self::LOCK_DAYS, self::DEFAULTS[self::LOCK_DAYS]);
    }

    /**
     * Percent of the reward paid at each level. Always sums to 100.
     *
     * @return array<int, float>
     */
    public function levelShares(): array
    {
        $shares = $this->settings->get(self::LEVEL_SHARES, self::DEFAULTS[self::LEVEL_SHARES]);

        if (is_string($shares)) {
            $shares = json_decode($shares, true);
        }

        if (! is_array($shares) || $shares === []) {
            $shares = self::DEFAULTS[self::LEVEL_SHARES];
        }

        $shares = array_values(array_map('floatval', $shares));

        return $this->normaliseShares($shares);
    }

    public function maxLevels(): int
    {
        return count($this->levelShares());
    }

    public function rewardMode(): string
    {
        $mode = (string) $this->raw(self::REWARD_MODE, self::DEFAULTS[self::REWARD_MODE]);

        return $mode === '' ? self::REWARD_MODE_ONCE_PER_PERSON : $mode;
    }

    public function adminCreditAllowed(): bool
    {
        return $this->bool(self::ALLOW_ADMIN_CREDIT);
    }

    public function blocksAccountDeletion(): bool
    {
        return $this->bool(self::BLOCK_ACCOUNT_DELETION);
    }

    public function creditMaxPercentOfOrder(): int
    {
        return (int) $this->raw(self::CREDIT_MAX_PERCENT_OF_ORDER, self::DEFAULTS[self::CREDIT_MAX_PERCENT_OF_ORDER]);
    }

    /**
     * What one level of one qualifying referral is worth.
     */
    public function amountForLevel(int $level): float
    {
        $shares = $this->levelShares();
        $index = $level - 1;

        if (! isset($shares[$index]) || $shares[$index] <= 0) {
            return 0.0;
        }

        return round($this->rewardAmount() * ($shares[$index] / 100), 2);
    }

    /**
     * Everything the admin settings form and the customer rules text need.
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->isEnabled(),
            'reward_amount' => $this->rewardAmount(),
            'threshold_amount' => $this->thresholdAmount(),
            'lock_days' => $this->lockDays(),
            'level_shares' => $this->levelShares(),
            'reward_mode' => $this->rewardMode(),
            'allow_admin_credit' => $this->adminCreditAllowed(),
            'block_account_deletion' => $this->blocksAccountDeletion(),
            'credit_max_percent_of_order' => $this->creditMaxPercentOfOrder(),
        ];
    }

    /**
     * The share table shown live in the admin form, e.g.
     * [['level' => 1, 'share' => 100.0, 'amount' => 10.0], ...]
     */
    public function levelBreakdown(): array
    {
        $reward = $this->rewardAmount();
        $rows = [];

        foreach ($this->levelShares() as $index => $share) {
            $rows[] = [
                'level' => $index + 1,
                'share' => $share,
                'amount' => round($reward * ($share / 100), 2),
            ];
        }

        return $rows;
    }

    /**
     * Save the whole set, normalising level_shares so it always sums to 100.
     */
    public function save(array $values): void
    {
        $this->settings->set(self::ENABLED, (bool) $values['enabled'], 'boolean', self::GROUP);
        $this->settings->set(self::REWARD_AMOUNT, (float) $values['reward_amount'], 'float', self::GROUP);
        $this->settings->set(self::THRESHOLD_AMOUNT, (float) $values['threshold_amount'], 'float', self::GROUP);
        $this->settings->set(self::LOCK_DAYS, (int) $values['lock_days'], 'integer', self::GROUP);

        $this->settings->set(
            self::LEVEL_SHARES,
            $this->normaliseShares((array) $values['level_shares']),
            'json',
            self::GROUP
        );

        $this->settings->set(self::REWARD_MODE, (string) $values['reward_mode'], 'string', self::GROUP);
        $this->settings->set(self::ALLOW_ADMIN_CREDIT, (bool) $values['allow_admin_credit'], 'boolean', self::GROUP);
        $this->settings->set(self::BLOCK_ACCOUNT_DELETION, (bool) $values['block_account_deletion'], 'boolean', self::GROUP);
        $this->settings->set(
            self::CREDIT_MAX_PERCENT_OF_ORDER,
            (int) $values['credit_max_percent_of_order'],
            'integer',
            self::GROUP
        );
    }

    /**
     * Coerce a share list into a clean 1-2 entry list that sums to 100.
     *
     * @param  array<int, mixed>  $shares
     * @return array<int, float>
     */
    protected function normaliseShares(array $shares): array
    {
        $shares = array_slice(array_values(array_map('floatval', array_filter(
            $shares,
            fn ($value) => is_numeric($value) && (float) $value >= 0
        ))), 0, 2);

        if ($shares === []) {
            return self::DEFAULTS[self::LEVEL_SHARES];
        }

        $total = array_sum($shares);

        if ($total <= 0) {
            return self::DEFAULTS[self::LEVEL_SHARES];
        }

        // Rescale so the levels always add up to the full reward.
        $rescaled = array_map(fn ($share) => round($share * (100 / $total), 4), $shares);
        $rescaled[count($rescaled) - 1] = round(100 - array_sum(array_slice($rescaled, 0, -1)), 4);

        return array_map(fn ($share) => round($share, 2), $rescaled);
    }

    protected function bool(string $key): bool
    {
        return (bool) $this->raw($key, self::DEFAULTS[$key]);
    }

    protected function float(string $key): float
    {
        return (float) $this->raw($key, self::DEFAULTS[$key]);
    }

    protected function raw(string $key, mixed $default): mixed
    {
        $value = $this->settings->get($key, $default);

        return $value ?? $default;
    }
}
