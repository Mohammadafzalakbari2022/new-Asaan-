<?php

namespace Cartxis\Identity\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdentityVerification extends Model
{
    protected $table = 'identity_verifications';

    /**
     * Never serialise the secrets. Every response this model reaches goes through
     * toArray()/toJson() somewhere, and a hidden column cannot leak from an API
     * resource that was written without thinking about it.
     */
    protected $hidden = [
        'national_id_encrypted',
        'national_id_fingerprint',
        'full_name_encrypted',
        'father_name_encrypted',
        'date_of_birth_encrypted',
        'face_match_reference_path',
    ];

    protected $fillable = [
        'user_id',
        'national_id_encrypted',
        'national_id_fingerprint',
        'full_name_encrypted',
        'father_name_encrypted',
        'date_of_birth_encrypted',
        'document_type',
        'image_disk',
        'image_path',
        'image_sha256',
        'image_purged_at',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'review_note',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'image_purged_at' => 'datetime',
        'face_match_score' => 'float',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /**
     * The only document type accepted today.
     */
    public const DOCUMENT_TAZKIRA = 'tazkira';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * False once the retention policy has deleted the image.
     */
    public function hasImage(): bool
    {
        return $this->image_purged_at === null && $this->image_path !== null;
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * The newest submission for this account, which is the one the customer page
     * shows. A rejection can be followed by another submission, so the queue
     * status lives on the row rather than on the account.
     */
    public function scopeLatestFor(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}