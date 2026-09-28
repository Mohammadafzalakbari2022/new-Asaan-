<?php

namespace Cartxis\Referral\Models;

use App\Models\User;
use Cartxis\Shop\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralLedgerEntry extends Model
{
    protected $table = 'referral_ledger';

    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'available_from',
        'balance_after',
        'commission_id',
        'order_id',
        'admin_id',
        'reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'available_from' => 'datetime',
    ];

    const TYPE_EARNED = 'earned';

    const TYPE_SPENT = 'spent';

    const TYPE_REVERSED = 'reversed';

    const TYPE_ADMIN_CREDIT = 'admin_credit';

    const TYPE_ADMIN_DEBIT = 'admin_debit';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function commission(): BelongsTo
    {
        return $this->belongsTo(ReferralCommission::class, 'commission_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function scopeAvailable($query)
    {
        return $query->where('available_from', '<=', now());
    }
}
