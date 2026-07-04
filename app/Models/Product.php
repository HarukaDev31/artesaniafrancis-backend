<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Product extends Model
{
    use HasSlug;

    public const LOW_STOCK_THRESHOLD = 5;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'price',
        'stock',
        'image_path',
        'is_active',
        'is_featured',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->latest();
    }

    public function latestStockMovement(): HasOne
    {
        return $this->hasOne(StockMovement::class)->latestOfMany();
    }

    public function scopeLowStock($query)
    {
        return $query
            ->where('stock', '>', 0)
            ->where('stock', '<=', self::LOW_STOCK_THRESHOLD);
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('stock', 0);
    }

    public function getStockStatusAttribute(): string
    {
        if ($this->stock === 0) {
            return 'out';
        }

        if ($this->stock <= self::LOW_STOCK_THRESHOLD) {
            return 'low';
        }

        return 'ok';
    }

    public function getStockStatusLabelAttribute(): string
    {
        return match ($this->stock_status) {
            'out' => 'Sin stock',
            'low' => 'Stock bajo',
            default => 'Disponible',
        };
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function getInStockAttribute(): bool
    {
        return $this->stock > 0;
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        if (Str::startsWith($this->image_path, ['http://', 'https://'])) {
            return $this->image_path;
        }

        $cdn = rtrim((string) config('filesystems.cdn_url'), '/');

        return $cdn.'/'.ltrim($this->image_path, '/');
    }
}
