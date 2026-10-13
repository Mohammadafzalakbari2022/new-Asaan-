<?php

namespace Cartxis\Marketing\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A message sent through the public contact form.
 *
 * This is the free "inbox": the website stores the message and it shows up in
 * the admin under Marketing > Messages. Nothing here talks to WhatsApp — a
 * WhatsApp message stays inside WhatsApp.
 */
class ContactMessage extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'contact_messages';

    const STATUS_NEW = 'new';

    const STATUS_READ = 'read';

    const STATUS_REPLIED = 'replied';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'status',
        'ip_address',
        'replied_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'replied_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope: filter by status.
     */
    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Mark the message as read — but never drag a replied message backwards.
     */
    public function markAsRead(): void
    {
        if ($this->status === self::STATUS_NEW) {
            $this->update(['status' => self::STATUS_READ]);
        }
    }
}
