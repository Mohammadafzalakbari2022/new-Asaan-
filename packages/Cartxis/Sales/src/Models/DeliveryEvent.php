<?php

namespace Cartxis\Sales\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryEvent extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'delivery_events';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'delivery_id',
        'actor_id',
        'from_status',
        'to_status',
        'note',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class, 'delivery_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function getStatusChangeAttribute(): ?string
    {
        if ($this->from_status && $this->to_status) {
            return ucfirst($this->from_status) . ' → ' . ucfirst($this->to_status);
        }

        return null;
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($event) {
            $event->created_at = now();
        });
    }
}