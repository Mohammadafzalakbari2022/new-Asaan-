<?php

declare(strict_types=1);

namespace Cartxis\Service\Models;

use App\Models\User;
use Cartxis\Shop\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One booked job.
 *
 * This is a financial record, so it is never soft deleted and never silently
 * removed. It always has exactly one order, written in the same transaction,
 * which is what puts the job in the existing Orders screens.
 */
class ServiceBooking extends Model
{
    public const STATUS_BOOKED = 'booked';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Statuses that cannot be moved any further.
     */
    public const TERMINAL_STATUSES = [
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    public const STATUSES = [
        self::STATUS_BOOKED,
        self::STATUS_ASSIGNED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_BOOKED => 'Booked',
        self::STATUS_ASSIGNED => 'Assigned',
        self::STATUS_IN_PROGRESS => 'In progress',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    protected $fillable = [
        'reference',
        'request_token',
        'order_id',
        'service_id',
        'user_id',
        'status',
        'assigned_to',
        'assigned_by',
        'assigned_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'scheduled_date',
        'scheduled_slot',
        'service_name',
        'price_snapshot',
        'price_unit',
        'amount_collected',
        'payment_method',
        'customer_name',
        'customer_phone',
        'customer_email',
        'address',
        'city',
        'notes',
        'internal_notes',
        'cancel_reason',
        'source',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'scheduled_date' => 'date',
        'price_snapshot' => 'decimal:2',
        'amount_collected' => 'decimal:2',
    ];

    protected $appends = ['status_label', 'price_display'];

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The worker doing the job. Always an existing delivery staff account.
     */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ServiceBookingEvent::class)->orderBy('created_at');
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        if (blank($status)) {
            return $query;
        }

        return $query->where('status', $status);
    }

    /**
     * Jobs that are still moving, i.e. everything the owner still has to act on.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', self::TERMINAL_STATUSES);
    }

    public function scopeForWorker(Builder $query, int $workerId): Builder
    {
        return $query->where('assigned_to', $workerId);
    }

    public function scopeScheduledBetween(Builder $query, $from, $to): Builder
    {
        if ($from) {
            $query->whereDate('scheduled_date', '>=', $from);
        }

        if ($to) {
            $query->whereDate('scheduled_date', '<=', $to);
        }

        return $query;
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%' . $term . '%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('reference', 'like', $like)
                ->orWhere('service_name', 'like', $like)
                ->orWhere('customer_name', 'like', $like)
                ->orWhere('customer_phone', 'like', $like);
        });
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->statusLabel();
    }

    public function getPriceDisplayAttribute(): string
    {
        $unit = Service::UNIT_LABELS[$this->price_unit] ?? Service::UNIT_LABELS[Service::UNIT_PER_JOB];

        return $this->price_snapshot . ' ' . $unit;
    }

    /**
     * What the worker took, defaulting to what was promised.
     */
    public function effectiveAmount(): float
    {
        if ($this->amount_collected !== null) {
            return (float) $this->amount_collected;
        }

        return (float) $this->price_snapshot;
    }

    /**
     * The gap between what was promised and what was taken. Non-zero means the
     * job ran over or under, which the owner needs to see.
     */
    public function amountVariance(): float
    {
        return round($this->effectiveAmount() - (float) $this->price_snapshot, 2);
    }

    public function hasVariance(): bool
    {
        return $this->amountVariance() !== 0.0;
    }
}
