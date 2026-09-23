<?php

namespace Cartxis\Sales\Services;

use Cartxis\Sales\Models\Delivery;
use Cartxis\Sales\Models\DeliveryEvent;
use Cartxis\Sales\Models\Shipment;
use Cartxis\Shop\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DeliveryStatusService
{
    /**
     * Map of allowed delivery status transitions.
     */
    protected array $allowedTransitions = [
        Delivery::STATUS_ASSIGNED => [
            Delivery::STATUS_OUT_FOR_DELIVERY,
            Delivery::STATUS_DELIVERED,
            Delivery::STATUS_UNDELIVERED,
            Delivery::STATUS_CANCELLED,
        ],
        Delivery::STATUS_OUT_FOR_DELIVERY => [
            Delivery::STATUS_ARRIVING,
            Delivery::STATUS_DELIVERED,
            Delivery::STATUS_UNDELIVERED,
            Delivery::STATUS_CANCELLED,
        ],
        Delivery::STATUS_ARRIVING => [
            Delivery::STATUS_DELIVERED,
            Delivery::STATUS_UNDELIVERED,
            Delivery::STATUS_CANCELLED,
        ],
        Delivery::STATUS_PENDING => [
            Delivery::STATUS_ASSIGNED,
            Delivery::STATUS_CANCELLED,
        ],
    ];

    /**
     * Assign a delivery run to a driver.
     */
    public function assign(Shipment $shipment, int $driverId, array $data = []): Delivery
    {
        if ($shipment->activeDelivery) {
            throw new \RuntimeException('This shipment already has an active delivery assignment.');
        }

        $order = $shipment->order;

        return DB::transaction(function () use ($shipment, $order, $driverId, $data) {
            $delivery = Delivery::create([
                'shipment_id' => $shipment->id,
                'order_id' => $order->id,
                'assigned_by' => Auth::id(),
                'assigned_to' => $driverId,
                'status' => Delivery::STATUS_ASSIGNED,
                'scheduled_date' => $data['scheduled_date'] ?? null,
                'priority' => $data['priority'] ?? 'normal',
                'customer_phone' => $data['customer_phone'] ?? $order->customer_phone,
                'cod_amount' => $data['cod_amount'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            if ($shipment->status === Shipment::STATUS_PENDING) {
                $shipment->update([
                    'status' => Shipment::STATUS_SHIPPED,
                    'shipped_at' => now(),
                ]);
            }

            $this->logEvent(
                $delivery,
                Delivery::STATUS_PENDING,
                Delivery::STATUS_ASSIGNED,
                'Assigned to delivery driver'
            );

            return $delivery;
        });
    }

    /**
     * Reassign a delivery to another driver.
     */
    public function reassign(Delivery $delivery, int $newDriverId, ?string $note = null): Delivery
    {
        if ($delivery->isTerminal()) {
            throw new \RuntimeException('A finished delivery cannot be reassigned.');
        }

        return DB::transaction(function () use ($delivery, $newDriverId, $note) {
            $delivery->update(['assigned_to' => $newDriverId]);

            $this->logEvent(
                $delivery,
                $delivery->status,
                $delivery->status,
                $note ?? 'Reassigned to another driver'
            );

            return $delivery->fresh();
        });
    }

    /**
     * Reschedule a delivery (change the scheduled date).
     */
    public function reschedule(Delivery $delivery, ?string $scheduledDate, ?string $note = null): Delivery
    {
        if ($delivery->isTerminal()) {
            throw new \RuntimeException('A finished delivery cannot be rescheduled.');
        }

        return DB::transaction(function () use ($delivery, $scheduledDate, $note) {
            $delivery->update(['scheduled_date' => $scheduledDate]);

            $this->logEvent(
                $delivery,
                $delivery->status,
                $delivery->status,
                $note ?? 'Delivery rescheduled'
            );

            return $delivery->fresh();
        });
    }

    /**
     * Apply a status transition with its side effects, guarded by the allowed map.
     */
    public function transition(Delivery $delivery, string $to, array $data = [], ?string $note = null): Delivery
    {
        $from = $delivery->status;

        if ($delivery->isTerminal()) {
            throw new \RuntimeException('This delivery is already finished.');
        }

        if (!in_array($to, $this->allowedTransitions[$from] ?? [], true)) {
            throw new \RuntimeException("Cannot move a delivery from {$from} to {$to}.");
        }

        return DB::transaction(function () use ($delivery, $from, $to, $data, $note) {
            $payload = [];

            if ($to === Delivery::STATUS_DELIVERED) {
                $payload['recipient_name'] = $data['recipient_name'] ?? null;
                $payload['delivered_photo_path'] = $data['delivered_photo_path'] ?? null;
                $payload['cod_received'] = $data['cod_received'] ?? $delivery->cod_amount ?? 0;

                $delivery->shipment->update([
                    'status' => Shipment::STATUS_DELIVERED,
                    'delivered_at' => now(),
                ]);

                $order = $delivery->order;
                (app(OrderService::class))->updateStatus($order, Order::STATUS_COMPLETED, 'Package delivered', false);
            } elseif ($to === Delivery::STATUS_OUT_FOR_DELIVERY) {
                $delivery->shipment->update(['status' => Shipment::STATUS_OUT_FOR_DELIVERY]);
            } elseif ($to === Delivery::STATUS_UNDELIVERED) {
                $payload['failure_reason'] = $data['failure_reason'] ?? null;
                $payload['failure_note'] = $data['failure_note'] ?? null;

                $delivery->shipment->update(['status' => Shipment::STATUS_FAILED]);
            } elseif ($to === Delivery::STATUS_CANCELLED) {
                $payload['failure_reason'] = $data['failure_reason'] ?? null;

                $delivery->shipment->update(['status' => Shipment::STATUS_CANCELLED]);
            }

            $payload['status'] = $to;
            $delivery->update($payload);

            $this->logEvent($delivery, $from, $to, $note);

            return $delivery->fresh();
        });
    }

    /**
     * Record a live driver location, throttled to keep the table calm.
     *
     * Accepts an update at most once every 15 seconds per delivery; earlier
     * pings are dropped silently (the caller keeps polling the same endpoint).
     */
    public function updateLocation(Delivery $delivery, float $latitude, float $longitude, ?float $accuracy = null): Delivery
    {
        $last = $delivery->last_location_at;

        if ($last !== null && $last->diffInSeconds(now()) < 15) {
            return $delivery;
        }

        return DB::transaction(function () use ($delivery, $latitude, $longitude) {
            $delivery->update([
                'last_latitude' => $latitude,
                'last_longitude' => $longitude,
                'last_location_at' => now(),
            ]);

            $this->logEvent($delivery, $delivery->status, $delivery->status, 'Live location updated');

            return $delivery->fresh();
        });
    }

    /**
     * Cancel a delivery (admin action).
     */
    public function cancel(Delivery $delivery, ?string $note = null): Delivery
    {
        return $this->transition($delivery, Delivery::STATUS_CANCELLED, note: $note);
    }

    /**
     * Append a delivery event row.
     */
    protected function logEvent(Delivery $delivery, string $from, string $to, ?string $note = null): DeliveryEvent
    {
        return DeliveryEvent::create([
            'delivery_id' => $delivery->id,
            'actor_id' => Auth::id(),
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
        ]);
    }
}