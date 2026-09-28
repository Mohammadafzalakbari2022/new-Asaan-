<?php

namespace Cartxis\Referral\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Referral extends Model
{
    protected $table = 'referrals';

    protected $fillable = [
        'referrer_user_id',
        'referred_user_id',
        'referral_code_id',
        'level',
        'status',
        'rewarded_at',
        'voided_reason',
        'voided_by',
        'voided_at',
    ];

    protected $casts = [
        'level' => 'integer',
        'rewarded_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    const STATUS_ACTIVE = 'active';

    const STATUS_VOIDED = 'voided';

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_user_id');
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function code(): BelongsTo
    {
        return $this->belongsTo(ReferralCode::class, 'referral_code_id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(ReferralCommission::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * The reward fires once per referred person, forever.
     */
    public function hasBeenRewarded(): bool
    {
        return $this->rewarded_at !== null;
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
