<?php

declare(strict_types=1);

namespace Cartxis\Service\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * One thing the store owner sells and a customer can book.
 *
 * Deliberately not a product. A service is never put in a basket and never
 * shipped, so it does not carry stock, weight, variants or an SKU. It has a
 * fixed price, a duration, a coverage area and a list of what is included.
 */
class Service extends Model
{
    use SoftDeletes;

    public const UNIT_PER_JOB = 'per_job';

    public const UNIT_PER_HOUR = 'per_hour';

    public const UNIT_PER_DAY = 'per_day';

    public const UNIT_PER_SQM = 'per_sqm';

    public const UNITS = [
        self::UNIT_PER_JOB,
        self::UNIT_PER_HOUR,
        self::UNIT_PER_DAY,
        self::UNIT_PER_SQM,
    ];

    public const UNIT_LABELS = [
        self::UNIT_PER_JOB => 'per job',
        self::UNIT_PER_HOUR => 'per hour',
        self::UNIT_PER_DAY => 'per day',
        self::UNIT_PER_SQM => 'per sqm',
    ];

    protected $fillable = [
        'service_category_id',
        'name',
        'slug',
        'short_description',
        'description',
        'includes',
        'excludes',
        'icon',
        'image',
        'icon_only',
        'price',
        'price_unit',
        'price_note',
        'duration_minutes',
        'duration_label',
        'service_area',
        'status',
        'featured',
        'booking_enabled',
        'sort_order',
        'meta_title',
        'meta_description',
        'meta_keywords',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'icon_only' => 'boolean',
        'featured' => 'boolean',
        'booking_enabled' => 'boolean',
        'sort_order' => 'integer',
        'duration_minutes' => 'integer',
        // Held as a comma-separated line in the database, but a list to everyone
        // reading or writing the service.
        'includes' => 'array',
        'excludes' => 'array',
    ];

    protected $appends = ['price_display', 'duration_display', 'image_url'];

    protected static function booted(): void
    {
        static::creating(function (self $service) {
            if (empty($service->slug)) {
                $service->slug = static::uniqueSlug($service->name);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(ServiceBooking::class);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('status', 'enabled');
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('featured', true);
    }

    public function scopeBookable(Builder $query): Builder
    {
        return $query->where('booking_enabled', true);
    }

    /**
     * What the storefront is allowed to show: published, and either booking it
     * or deliberately published for display only.
     */
    public function scopePublished(Builder $query): Builder
    {
        // Hiding a category has to hide what is inside it, otherwise the owner
        // switches a category off and its services keep selling themselves.
        return $query->enabled()->whereHas(
            'category',
            fn (Builder $q) => $q->where('status', 'enabled')
        );
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%' . $term . '%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('name', 'like', $like)
                ->orWhere('short_description', 'like', $like)
                ->orWhere('description', 'like', $like);
        });
    }

    public function isBookable(): bool
    {
        return $this->booking_enabled && $this->status === 'enabled';
    }

    public function hasBookings(): bool
    {
        return $this->bookings()->exists();
    }

    public function unitLabel(): string
    {
        return self::UNIT_LABELS[$this->price_unit] ?? self::UNIT_LABELS[self::UNIT_PER_JOB];
    }

    /**
     * The photo as a full address, ready for an <img> tag.
     *
     * Kept apart from `image`, which stays the stored path, because the admin
     * edit form needs that path to show what is already uploaded.
     */
    public function getImageUrlAttribute(): ?string
    {
        if (blank($this->image)) {
            return null;
        }

        if (filter_var($this->image, FILTER_VALIDATE_URL)) {
            return $this->image;
        }

        return asset('storage/' . ltrim($this->image, '/'));
    }

    /**
     * The price as shown to a customer, with its unit.
     */
    public function getPriceDisplayAttribute(): string
    {
        return $this->price . ' ' . $this->unitLabel();
    }

    /**
     * How long the job takes, preferring the owner's own wording.
     */
    public function getDurationDisplayAttribute(): ?string
    {
        if (filled($this->duration_label)) {
            return $this->duration_label;
        }

        if (! $this->duration_minutes) {
            return null;
        }

        $minutes = (int) $this->duration_minutes;

        if ($minutes < 60) {
            return $minutes . ' min';
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        if ($rest === 0) {
            return $hours === 1 ? '1 hour' : $hours . ' hours';
        }

        return $hours === 1
            ? '1 hour ' . $rest . ' min'
            : $hours . ' hours ' . $rest . ' min';
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'service';
        $slug = $base;
        $suffix = 2;

        while (static::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists()
        ) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }
}
