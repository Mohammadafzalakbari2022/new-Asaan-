<?php

namespace Cartxis\Sales\Models;

use App\Models\User;
use Cartxis\Shop\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Delivery extends Model
{
    const STATUS_PENDING = 'pending';
    const STATUS_ASSIGNED = 'assigned';
    const STATUS_OUT_FOR_DELIVERY = 'out_for_delivery';
    const STATUS_ARRIVING = 'arriving';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_UNDELIVERED = 'undelivered';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'shipment_id',
        'order_id',
        'assigned_by',
        'assigned_to',
        'status',
        'scheduled_date',
        'priority',
        'customer_phone',
        'cod_amount',
        'cod_received',
        'recipient_name',
        'delivered_photo_path',
        'failure_reason',
        'failure_note',
        'last_latitude',
        'last_longitude',
        'last_location_at',
        'notes',
    ];

    protected $casts = [
        'scheduled_date' => 'datetime',
        'cod_amount' => 'decimal:2',
        'cod_received' => 'decimal:2',
        'last_latitude' => 'decimal:7',
        'last_longitude' => 'decimal:7',
        'last_location_at' => 'datetime',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function events(): HasMany
    {
        return $this->hasMany(DeliveryEvent::class, 'delivery_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, [
            self::STATUS_ASSIGNED,
            self::STATUS_OUT_FOR_DELIVERY,
            self::STATUS_ARRIVING,
        ]);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_DELIVERED,
            self::STATUS_UNDELIVERED,
            self::STATUS_CANCELLED,
        ]);
    }

    public function getStatusBadgeAttribute(): array
    {
        return match ($this->status) {
            self::STATUS_PENDING => ['label' => 'Pending', 'class' => 'bg-yellow-100 text-yellow-800 border border-yellow-200'],
            self::STATUS_ASSIGNED => ['label' => 'Assigned', 'class' => 'bg-blue-100 text-blue-800 border border-blue-200'],
            self::STATUS_OUT_FOR_DELIVERY => ['label' => 'Out for Delivery', 'class' => 'bg-purple-100 text-purple-800 border border-purple-200'],
            self::STATUS_ARRIVING => ['label' => 'Arriving Today', 'class' => 'bg-indigo-100 text-indigo-800 border border-indigo-200'],
            self::STATUS_DELIVERED => ['label' => 'Delivered', 'class' => 'bg-green-100 text-green-800 border border-green-200'],
            self::STATUS_UNDELIVERED => ['label' => 'Undelivered', 'class' => 'bg-red-100 text-red-800 border border-red-200'],
            self::STATUS_CANCELLED => ['label' => 'Cancelled', 'class' => 'bg-gray-100 text-gray-800 border border-gray-200'],
            default => ['label' => 'Unknown', 'class' => 'bg-gray-100 text-gray-800 border border-gray-200'],
        };
    }

    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_ASSIGNED => 'Assigned',
            self::STATUS_OUT_FOR_DELIVERY => 'Out for Delivery',
            self::STATUS_ARRIVING => 'Arriving Today',
            self::STATUS_DELIVERED => 'Delivered',
            self::STATUS_UNDELIVERED => 'Undelivered',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    public static function getFailureReasons(): array
    {
        return [
            'customer_unavailable' => 'Customer Unavailable',
            'wrong_address' => 'Wrong Address',
            'no_response' => 'No Response',
            'other' => 'Other',
        ];
    }
}