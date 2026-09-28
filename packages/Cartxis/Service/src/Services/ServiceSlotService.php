<?php

declare(strict_types=1);

namespace Cartxis\Service\Services;

use Cartxis\Service\Models\ServiceBooking;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * When a job may be booked for.
 *
 * The rules live here rather than in the Vue form, because the form is only a
 * convenience: a hand-made request is checked against exactly the same rules.
 */
class ServiceSlotService
{
    public function __construct(protected ServiceSettings $settings) {}

    /**
     * The earliest date a customer may pick.
     */
    public function earliestDate(): CarbonImmutable
    {
        $today = CarbonImmutable::today();

        if ($this->settings->sameDayAllowed()) {
            return $today;
        }

        return $today->addHours($this->settings->leadTimeHours())->startOfDay();
    }

    /**
     * The last date a customer may pick.
     */
    public function latestDate(): CarbonImmutable
    {
        return CarbonImmutable::today()->addDays(
            max(1, (int) ceil($this->settings->bookingWindowHours() / 24))
        );
    }

    public function isBookableDate(string $date): bool
    {
        try {
            $day = CarbonImmutable::parse($date)->startOfDay();
        } catch (\Throwable) {
            return false;
        }

        return $day->greaterThanOrEqualTo($this->earliestDate())
            && $day->lessThanOrEqualTo($this->latestDate());
    }

    /**
     * A plain-English reason the date was refused, for the message shown under
     * the date field. Null means the date is fine.
     */
    public function dateError(string $date): ?string
    {
        try {
            $day = CarbonImmutable::parse($date)->startOfDay();
        } catch (\Throwable) {
            return 'That date could not be read.';
        }

        $earliest = $this->earliestDate();

        if ($day->lessThan($earliest)) {
            return $this->settings->sameDayAllowed()
                ? 'Please choose today or a later date.'
                : 'The earliest date you can book is ' . $earliest->format('j M Y') . '.';
        }

        if ($day->greaterThan($this->latestDate())) {
            return 'Bookings are open up to ' . $this->latestDate()->format('j M Y') . '.';
        }

        return null;
    }

    public function isBookableSlot(string $label, string $date): bool
    {
        return in_array($label, $this->settings->timeSlotLabels(), true)
            && $this->hasCapacity($date, $label);
    }

    public function hasCapacity(string $date, string $slotLabel): bool
    {
        $taken = ServiceBooking::query()
            ->whereDate('scheduled_date', $date)
            ->where('scheduled_slot', $slotLabel)
            ->open()
            ->count();

        return $taken < $this->settings->capacityPerSlot();
    }

    /**
     * The slots a customer may pick for a given day, each one marked full or not.
     *
     * A slot in the past today is not offered at all, so nobody is offered a
     * time that has already gone by.
     *
     * @return Collection<int, array{label: string, start: string, end: string, available: bool, full: bool}>
     */
    public function availableSlots(string $date): Collection
    {
        $isToday = CarbonImmutable::parse($date)->isToday();
        $now = CarbonImmutable::now();

        return collect($this->settings->timeSlots())
            ->reject(fn (array $slot) => $isToday && $this->slotHasPassed($slot, $now))
            ->map(function (array $slot) use ($date) {
                $full = ! $this->hasCapacity($date, $slot['label']);

                return [
                    'label' => $slot['label'],
                    'start' => $slot['start'],
                    'end' => $slot['end'],
                    'available' => ! $full,
                    'full' => $full,
                ];
            })
            ->values();
    }

    /**
     * True when a slot's start time today has already gone by, so nobody is
     * offered a time that has passed.
     */
    protected function slotHasPassed(array $slot, CarbonImmutable $now): bool
    {
        $start = $slot['start'] ?? '';

        if (! preg_match('/^\d{2}:\d{2}$/', $start)) {
            return false;
        }

        [$hour, $minute] = array_map('intval', explode(':', $start));

        return CarbonImmutable::today()->setTime($hour, $minute)->lessThanOrEqualTo($now);
    }
}
