<?php

declare(strict_types=1);

namespace Cartxis\Service\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line in a booking's history: who moved it, from what, to what, and why.
 *
 * Shaped exactly like Cartxis\Sales\Models\DeliveryEvent so the timeline reads
 * the same way on both the delivery screens and the service job screens.
 */
class ServiceBookingEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'service_booking_id',
        'actor_id',
        'from_status',
        'to_status',
        'note',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(ServiceBooking::class, 'service_booking_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function fromStatusLabel(): ?string
    {
        return $this->from_status
            ? (ServiceBooking::STATUS_LABELS[$this->from_status] ?? $this->from_status)
            : null;
    }

    public function toStatusLabel(): ?string
    {
        return $this->to_status
            ? (ServiceBooking::STATUS_LABELS[$this->to_status] ?? $this->to_status)
            : null;
    }
}
