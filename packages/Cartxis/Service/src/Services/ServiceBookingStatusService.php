<?php

declare(strict_types=1);

namespace Cartxis\Service\Services;

use App\Models\User;
use Cartxis\Service\Models\ServiceBooking;
use Cartxis\Service\Models\ServiceBookingEvent;
use Cartxis\Shop\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The lifecycle of a booked job, and the order row that follows it.
 *
 * Two things matter here and both are deliberate:
 *
 * 1. Status can only move one step at a time through the map below, and only
 *    the transitions listed are allowed. A job cannot skip from "booked" straight
 *    to "in progress", and a finished job cannot be reopened. The same rule is
 *    re-checked on the server, so the buttons in the worker app are a
 *    convenience and never the guard.
 *
 * 2. The order row is always written with model events suppressed. The referral
 *    programme watches every order whose payment_status becomes "paid", and a
 *    service job is not a shop order: the money for it is cash collected on the
 *    day, not a basket checkout, so it must never earn referral commission.
 *    Suppressing the events is what keeps it out, without changing anything in
 *    the referral package.
 */
class ServiceBookingStatusService
{
    /**
     * Where a job is allowed to go next.
     */
    protected array $allowedTransitions = [
        ServiceBooking::STATUS_BOOKED => [
            ServiceBooking::STATUS_ASSIGNED,
            ServiceBooking::STATUS_IN_PROGRESS,
            ServiceBooking::STATUS_CANCELLED,
        ],
        ServiceBooking::STATUS_ASSIGNED => [
            ServiceBooking::STATUS_IN_PROGRESS,
            ServiceBooking::STATUS_CANCELLED,
        ],
        ServiceBooking::STATUS_IN_PROGRESS => [
            ServiceBooking::STATUS_COMPLETED,
            ServiceBooking::STATUS_CANCELLED,
        ],
        ServiceBooking::STATUS_COMPLETED => [],
        ServiceBooking::STATUS_CANCELLED => [],
    ];

    public function allowedTransitionsFor(string $status): array
    {
        return $this->allowedTransitions[$status] ?? [];
    }

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, $this->allowedTransitionsFor($from), true);
    }

    /**
     * The status buttons a given screen may show for this job right now.
     */
    public function nextStatusesFor(ServiceBooking $booking): array
    {
        if ($booking->isTerminal()) {
            return [];
        }

        return array_values(array_filter(
            $this->allowedTransitionsFor($booking->status),
            fn (string $status) => $status !== ServiceBooking::STATUS_ASSIGNED
                || $booking->assigned_to !== null
        ));
    }

    /**
     * Give the job to one of the existing delivery staff.
     */
    public function assign(ServiceBooking $booking, int $workerId, ?string $note = null): ServiceBooking
    {
        if ($booking->isTerminal()) {
            throw new RuntimeException('A finished job cannot be given to a worker.');
        }

        $worker = User::find($workerId);

        if (! $worker) {
            throw new RuntimeException('That worker no longer exists.');
        }

        // Checked here as well as on the screen. A stale page, or a call that
        // skips the form entirely, must not be able to hand a job to somebody
        // who is not on the staff list.
        if ($worker->role !== 'delivery') {
            throw new RuntimeException('A job can only be given to one of your existing delivery staff.');
        }

        if (! $worker->is_active) {
            throw new RuntimeException('That worker is no longer active. Reactivate them first.');
        }

        return DB::transaction(function () use ($booking, $worker, $note) {
            $booking->assigned_to = $worker->id;
            $booking->assigned_by = Auth::id();
            $booking->assigned_at = now();

            // Assigning an already assigned job is a reassignment, not a status
            // change, so it must not reset a job that is under way.
            if ($booking->status === ServiceBooking::STATUS_BOOKED) {
                $booking->status = ServiceBooking::STATUS_ASSIGNED;
            }

            $booking->save();

            $this->recordEvent($booking, $note ?: 'Assigned to ' . ($worker->name ?: 'a worker') . '.');

            $this->syncOrder($booking);

            return $booking->fresh(['worker', 'events']);
        });
    }

    /**
     * Take a worker off a job without cancelling it.
     */
    public function unassign(ServiceBooking $booking, ?string $note = null): ServiceBooking
    {
        if ($booking->isTerminal()) {
            throw new RuntimeException('A finished job cannot be reassigned.');
        }

        return DB::transaction(function () use ($booking, $note) {
            $booking->assigned_to = null;
            $booking->assigned_by = null;
            $booking->assigned_at = null;

            if ($booking->status === ServiceBooking::STATUS_ASSIGNED) {
                $booking->status = ServiceBooking::STATUS_BOOKED;
            }

            $booking->save();

            $this->recordEvent($booking, $note ?: 'Worker removed.');

            $this->syncOrder($booking);

            return $booking->fresh(['worker', 'events']);
        });
    }

    public function start(ServiceBooking $booking, ?string $note = null): ServiceBooking
    {
        $this->assertTransition($booking, ServiceBooking::STATUS_IN_PROGRESS);

        return DB::transaction(function () use ($booking, $note) {
            $booking->status = ServiceBooking::STATUS_IN_PROGRESS;
            $booking->started_at = now();
            $booking->save();

            $this->recordEvent($booking, $note ?: 'Work started.');
            $this->syncOrder($booking);

            return $booking->fresh(['worker', 'events']);
        });
    }

    /**
     * Finish the job and record what was actually taken.
     *
     * The amount is required. A job that was finished but never charged for is
     * the single easiest way for a job to go missing money, so it is not
     * allowed to happen silently.
     */
    public function complete(
        ServiceBooking $booking,
        float $amountCollected,
        ?string $note = null,
    ): ServiceBooking {
        $this->assertTransition($booking, ServiceBooking::STATUS_COMPLETED);

        if ($amountCollected < 0) {
            throw new RuntimeException('The amount collected cannot be negative.');
        }

        return DB::transaction(function () use ($booking, $amountCollected, $note) {
            $booking->status = ServiceBooking::STATUS_COMPLETED;
            $booking->amount_collected = round($amountCollected, 2);
            $booking->completed_at = now();
            $booking->save();

            $variance = $booking->amountVariance();
            $message = $note ?: 'Job finished.';

            if ($variance !== 0.0) {
                $message .= sprintf(
                    ' Collected %s against a promised %s.',
                    $booking->amount_collected,
                    $booking->price_snapshot,
                );
            }

            $this->recordEvent($booking, $message);
            $this->syncOrder($booking);

            return $booking->fresh(['worker', 'events']);
        });
    }

    public function cancel(ServiceBooking $booking, string $reason): ServiceBooking
    {
        $this->assertTransition($booking, ServiceBooking::STATUS_CANCELLED);

        return DB::transaction(function () use ($booking, $reason) {
            $booking->status = ServiceBooking::STATUS_CANCELLED;
            $booking->cancel_reason = $reason;
            $booking->cancelled_at = now();
            $booking->save();

            $this->recordEvent($booking, 'Cancelled: ' . $reason);
            $this->syncOrder($booking);

            return $booking->fresh(['worker', 'events']);
        });
    }

    /**
     * An owner-only note. Never shown to the customer.
     */
    public function addInternalNote(ServiceBooking $booking, string $note): void
    {
        $booking->internal_notes = trim(($booking->internal_notes ? $booking->internal_notes . "\n" : '') . $note);
        $booking->saveQuietly();
    }

    public function recordEvent(ServiceBooking $booking, ?string $note = null, ?string $toStatus = null): ServiceBookingEvent
    {
        return ServiceBookingEvent::create([
            'service_booking_id' => $booking->id,
            'actor_id' => Auth::id(),
            'from_status' => $booking->wasChanged('status') ? $booking->getOriginal('status') : $booking->status,
            'to_status' => $toStatus ?? $booking->status,
            'note' => $note,
            'created_at' => now(),
        ]);
    }

    /**
     * Keep the order row in step with the job.
     *
     * Every write here is quiet on purpose. See the class docblock: a service
     * order must not touch the referral programme.
     */
    public function syncOrder(ServiceBooking $booking): void
    {
        $order = $booking->order;

        if (! $order) {
            return;
        }

        $attributes = [
            'status' => $this->orderStatusFor($booking),
            'total' => $booking->effectiveAmount(),
            'subtotal' => $booking->effectiveAmount(),
        ];

        if ($booking->status === ServiceBooking::STATUS_COMPLETED) {
            $attributes['payment_status'] = Order::PAYMENT_PAID;
        }

        $order->updateQuietly($attributes);
    }

    /**
     * A job's order carries the same lifecycle the job does, using the order
     * statuses the rest of the platform already understands.
     */
    public function orderStatusFor(ServiceBooking $booking): string
    {
        return match ($booking->status) {
            ServiceBooking::STATUS_BOOKED => Order::STATUS_PENDING,
            ServiceBooking::STATUS_ASSIGNED,
            ServiceBooking::STATUS_IN_PROGRESS => Order::STATUS_PROCESSING,
            ServiceBooking::STATUS_COMPLETED => Order::STATUS_COMPLETED,
            ServiceBooking::STATUS_CANCELLED => Order::STATUS_CANCELLED,
            default => Order::STATUS_PENDING,
        };
    }

    /**
     * The reason a status change was refused, for the message shown to the user.
     */
    protected function assertTransition(ServiceBooking $booking, string $to): void
    {
        if ($booking->isTerminal()) {
            throw new RuntimeException(
                'This job is already ' . strtolower($booking->statusLabel()) . ' and cannot change.'
            );
        }

        if (! $this->canTransition($booking->status, $to)) {
            throw new RuntimeException(
                'A job that is ' . strtolower($booking->statusLabel()) . ' cannot become '
                . strtolower(ServiceBooking::STATUS_LABELS[$to] ?? $to) . '.'
            );
        }

        if ($to === ServiceBooking::STATUS_IN_PROGRESS && $booking->assigned_to === null) {
            throw new RuntimeException('Assign a worker to this job before it can be started.');
        }
    }
}
