<?php

declare(strict_types=1);

namespace Cartxis\Service\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ServiceCategory extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'icon',
        'image',
        'status',
        'sort_order',
        'show_in_menu',
        'meta_title',
        'meta_description',
        'meta_keywords',
    ];

    protected $casts = [
        'status' => 'string',
        'sort_order' => 'integer',
        'show_in_menu' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $category) {
            if (empty($category->slug)) {
                $category->slug = static::uniqueSlug($category->name);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('sort_order');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('status', 'enabled');
    }

    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Enabled categories that a visitor can actually be shown.
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->enabled()->orderBy('sort_order')->orderBy('name');
    }

    public function hasServices(): bool
    {
        return $this->services()->exists();
    }

    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Every category underneath this one, at any depth.
     *
     * Used in two places: to refuse filing a category under its own descendant,
     * and to keep those descendants out of its own parent dropdown. The walk is
     * recursive rather than one level deep so a deeper tree cannot be walked
     * into a circle.
     *
     * @return array<int, int>
     */
    public function descendantIds(): array
    {
        $ids = [];

        $walk = function (?int $parentId) use (&$walk, &$ids): void {
            foreach (static::where('parent_id', $parentId)->pluck('id') as $childId) {
                $childId = (int) $childId;

                if (in_array($childId, $ids, true)) {
                    continue;
                }

                $ids[] = $childId;
                $walk($childId);
            }
        };

        $walk((int) $this->getKey());

        return $ids;
    }

    /**
     * Whether this category, or anything under it, still has services in it.
     * Used so a category is never deleted out from under a live service.
     */
    public function hasServicesAnywhere(): bool
    {
        if ($this->hasServices()) {
            return true;
        }

        foreach ($this->children()->with('children')->get() as $child) {
            if ($child->hasServicesAnywhere()) {
                return true;
            }
        }

        return false;
    }

    public function getFullNameAttribute(): string
    {
        return $this->parent
            ? $this->parent->name . ' → ' . $this->name
            : $this->name;
    }

    /**
     * A slug that is not already taken, so two categories with the same name
     * can both exist.
     */
    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'service-category';
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
