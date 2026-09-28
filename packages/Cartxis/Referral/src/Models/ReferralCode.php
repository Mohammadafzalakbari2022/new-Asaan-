<?php

namespace Cartxis\Referral\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReferralCode extends Model
{
    protected $table = 'referral_codes';

    protected $fillable = [
        'user_id',
        'code',
        'status',
        'clicks',
    ];

    protected $casts = [
        'clicks' => 'integer',
    ];

    const STATUS_ACTIVE = 'active';

    const STATUS_DISABLED = 'disabled';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function recordClick(): void
    {
        $this->increment('clicks');
    }
}
