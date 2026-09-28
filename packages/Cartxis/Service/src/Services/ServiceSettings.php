<?php

declare(strict_types=1);

namespace Cartxis\Service\Services;

use Cartxis\Core\Services\SettingService;

/**
 * Typed access to the services settings.
 *
 * Every value has a baked-in default so the catalogue and the booking form
 * behave correctly even before the settings migration has run, and can never
 * crash the app because a row was deleted by hand.
 */
class ServiceSettings
{
    public const GROUP = 'service';

    public const BOOKING_ENABLED = 'service.booking_enabled';
    public const LEAD_TIME_HOURS = 'service.lead_time_hours';
    public const BOOKING_WINDOW_HOURS = 'service.booking_window_hours';
    public const SAME_DAY_ALLOWED = 'service.same_day_allowed';
    public const TIME_SLOTS = 'service.time_slots';
    public const CAPACITY_PER_SLOT = 'service.capacity_per_slot';
    public const COVERAGE_NOTE = 'service.coverage_note';
    public const CONTACT_PHONE = 'service.contact_phone';
    public const CONTACT_WHATSAPP = 'service.contact_whatsapp';
    public const REQUIRE_LOGIN_TO_BOOK = 'service.require_login_to_book';
    public const AUTO_ASSIGN = 'service.auto_assign';
    public const REFERENCE_PREFIX = 'service.reference_prefix';

    /**
     * Defaults, mirrored by the settings seed migration.
     */
    public const DEFAULTS = [
        self::BOOKING_ENABLED => true,
        self::LEAD_TIME_HOURS => 24,
        self::BOOKING_WINDOW_HOURS => 168,
        self::SAME_DAY_ALLOWED => false,
        self::TIME_SLOTS => [
            ['label' => 'Morning', 'start' => '08:00', 'end' => '12:00'],
            ['label' => 'Afternoon', 'start' => '12:00', 'end' => '16:00'],
            ['label' => 'Evening', 'start' => '16:00', 'end' => '20:00'],
        ],
        self::CAPACITY_PER_SLOT => 10,
        self::COVERAGE_NOTE => '',
        self::CONTACT_PHONE => '',
        self::CONTACT_WHATSAPP => '',
        self::REQUIRE_LOGIN_TO_BOOK => false,
        self::AUTO_ASSIGN => false,
        self::REFERENCE_PREFIX => 'SRV-',
    ];

    public function __construct(protected SettingService $settings) {}

    public function isBookingEnabled(): bool
    {
        return $this->bool(self::BOOKING_ENABLED);
    }

    public function leadTimeHours(): int
    {
        return (int) $this->raw(self::LEAD_TIME_HOURS, self::DEFAULTS[self::LEAD_TIME_HOURS]);
    }

    public function bookingWindowHours(): int
    {
        return (int) $this->raw(self::BOOKING_WINDOW_HOURS, self::DEFAULTS[self::BOOKING_WINDOW_HOURS]);
    }

    public function sameDayAllowed(): bool
    {
        return $this->bool(self::SAME_DAY_ALLOWED);
    }

    public function capacityPerSlot(): int
    {
        return max(1, (int) $this->raw(self::CAPACITY_PER_SLOT, self::DEFAULTS[self::CAPACITY_PER_SLOT]));
    }

    public function requiresLoginToBook(): bool
    {
        return $this->bool(self::REQUIRE_LOGIN_TO_BOOK);
    }

    public function autoAssign(): bool
    {
        return $this->bool(self::AUTO_ASSIGN);
    }

    public function coverageNote(): string
    {
        return trim((string) $this->raw(self::COVERAGE_NOTE, self::DEFAULTS[self::COVERAGE_NOTE]));
    }

    public function contactPhone(): string
    {
        return trim((string) $this->raw(self::CONTACT_PHONE, self::DEFAULTS[self::CONTACT_PHONE]));
    }

    public function contactWhatsapp(): string
    {
        return trim((string) $this->raw(self::CONTACT_WHATSAPP, self::DEFAULTS[self::CONTACT_WHATSAPP]));
    }

    public function referencePrefix(): string
    {
        $prefix = trim((string) $this->raw(self::REFERENCE_PREFIX, self::DEFAULTS[self::REFERENCE_PREFIX]));

        return $prefix !== '' ? $prefix : self::DEFAULTS[self::REFERENCE_PREFIX];
    }

    /**
     * The time slots the owner offers, always as a clean list of
     * label/start/end. Anything half-configured is dropped rather than shown
     * to a customer as a broken choice.
     *
     * @return array<int, array{label: string, start: string, end: string}>
     */
    public function timeSlots(): array
    {
        $slots = $this->raw(self::TIME_SLOTS, self::DEFAULTS[self::TIME_SLOTS]);

        if (is_string($slots)) {
            $slots = json_decode($slots, true);
        }

        if (! is_array($slots)) {
            $slots = self::DEFAULTS[self::TIME_SLOTS];
        }

        $clean = [];

        foreach ($slots as $slot) {
            if (! is_array($slot)) {
                continue;
            }

            $start = (string) ($slot['start'] ?? '');
            $end = (string) ($slot['end'] ?? '');

            if ($start === '' || $end === '') {
                continue;
            }

            $clean[] = [
                'label' => (string) ($slot['label'] ?? $start . ' - ' . $end),
                'start' => $start,
                'end' => $end,
            ];
        }

        return $clean !== [] ? $clean : self::DEFAULTS[self::TIME_SLOTS];
    }

    /**
     * The labels a customer is allowed to submit, so a hand-made request cannot
     * invent a slot that does not exist.
     *
     * @return array<int, string>
     */
    public function timeSlotLabels(): array
    {
        return array_map(fn (array $slot) => $slot['label'], $this->timeSlots());
    }

    public function all(): array
    {
        return [
            'booking_enabled' => $this->isBookingEnabled(),
            'lead_time_hours' => $this->leadTimeHours(),
            'booking_window_hours' => $this->bookingWindowHours(),
            'same_day_allowed' => $this->sameDayAllowed(),
            'time_slots' => $this->timeSlots(),
            'capacity_per_slot' => $this->capacityPerSlot(),
            'coverage_note' => $this->coverageNote(),
            'contact_phone' => $this->contactPhone(),
            'contact_whatsapp' => $this->contactWhatsapp(),
            'require_login_to_book' => $this->requiresLoginToBook(),
            'auto_assign' => $this->autoAssign(),
            'reference_prefix' => $this->referencePrefix(),
        ];
    }

    public function save(array $values): void
    {
        $this->settings->set(self::BOOKING_ENABLED, (bool) $values['booking_enabled'], 'boolean', self::GROUP);
        $this->settings->set(self::LEAD_TIME_HOURS, (int) $values['lead_time_hours'], 'integer', self::GROUP);
        $this->settings->set(self::BOOKING_WINDOW_HOURS, (int) $values['booking_window_hours'], 'integer', self::GROUP);
        $this->settings->set(self::SAME_DAY_ALLOWED, (bool) $values['same_day_allowed'], 'boolean', self::GROUP);
        $this->settings->set(self::CAPACITY_PER_SLOT, (int) $values['capacity_per_slot'], 'integer', self::GROUP);
        $this->settings->set(self::COVERAGE_NOTE, (string) $values['coverage_note'], 'string', self::GROUP);
        $this->settings->set(self::CONTACT_PHONE, (string) $values['contact_phone'], 'string', self::GROUP);
        $this->settings->set(self::CONTACT_WHATSAPP, (string) $values['contact_whatsapp'], 'string', self::GROUP);
        $this->settings->set(
            self::REQUIRE_LOGIN_TO_BOOK,
            (bool) $values['require_login_to_book'],
            'boolean',
            self::GROUP
        );
        $this->settings->set(self::AUTO_ASSIGN, (bool) $values['auto_assign'], 'boolean', self::GROUP);
        $this->settings->set(self::REFERENCE_PREFIX, (string) $values['reference_prefix'], 'string', self::GROUP);

        // The slot editor is a small repeating set of label/start/end rows.
        $slots = [];

        foreach ((array) ($values['time_slots'] ?? []) as $slot) {
            if (! is_array($slot)) {
                continue;
            }

            $start = trim((string) ($slot['start'] ?? ''));
            $end = trim((string) ($slot['end'] ?? ''));

            if ($start === '' || $end === '') {
                continue;
            }

            $slots[] = [
                'label' => trim((string) ($slot['label'] ?? '')) ?: $start . ' - ' . $end,
                'start' => $start,
                'end' => $end,
            ];
        }

        $this->settings->set(
            self::TIME_SLOTS,
            $slots !== [] ? $slots : self::DEFAULTS[self::TIME_SLOTS],
            'json',
            self::GROUP
        );
    }

    protected function bool(string $key): bool
    {
        return (bool) $this->raw($key, self::DEFAULTS[$key]);
    }

    protected function raw(string $key, mixed $default): mixed
    {
        $value = $this->settings->get($key, $default);

        return $value ?? $default;
    }
}
