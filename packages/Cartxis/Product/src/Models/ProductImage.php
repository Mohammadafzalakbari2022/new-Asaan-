<?php

namespace Cartxis\Product\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'path',
        'thumbnail_path',
        'alt_text',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    /**
     * Attributes to append
     */
    protected $appends = ['url'];

    /**
     * Get the product
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the full URL for the image
     */
    public function getUrlAttribute(): string
    {
        return (string) static::urlFor($this->path);
    }

    /**
     * Turn any stored image path into a full URL.
     *
     * Three kinds of path reach this method:
     *  - an outside link ("https://...", "//...") is returned untouched;
     *  - a path that starts with "/" is a file shipped in public/ (for example
     *    "/images/products/phone.jpg"), so it is served straight from the app;
     *  - anything else is a file on the public storage disk ("products/x.jpg")
     *    and is served through the /storage symlink.
     *
     * Without the middle case, an outside link would be mangled into
     * "/storage/https://..." and never load.
     */
    public static function urlFor(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        if (str_starts_with($path, '/')) {
            return asset(ltrim($path, '/'));
        }

        return asset('storage/'.$path);
    }
}
