<?php

namespace Cartxis\Referral\Models;

use App\Models\User;
use Cartxis\Shop\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReferralCommission extends Model
{
    protected $table = 'referral_commissions';

    protected $fillable = [
        'referral_id',
        'referrer_user_id',
        'order_id',
        'level',
        'amount',
        'reward_snapshot',
        'share_snapshot',
        'status',
        'unlocks_at',
        'reversed_at',
        'reversal_reason',
    ];

    protected $casts = [
        'level' => 'integer',
        'amount' => 'decimal:2',
        'reward_snapshot' => 'decimal:2',
        'share_snapshot' => 'decimal:2',
        'unlocks_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    const STATUS_ACTIVE = 'active';

    const STATUS_REVERSED = 'reversed';

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_user_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(ReferralLedgerEntry::class, 'commission_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeLocked($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->whereNotNull('unlocks_at')
            ->where('unlocks_at', '>', now());
    }

    public function isReversed(): bool
    {
        return $this->status === self::STATUS_REVERSED;
    }

    public function isLocked(): bool
    {
        return $this->unlocks_at !== null && $this->unlocks_at->isFuture();
    }
}
