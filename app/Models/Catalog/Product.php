<?php

namespace App\Models\Catalog;

use App\Models\Orders\OrderItem;
use App\Models\Storefront\VisualSearchIndexEntry;
use Database\Factories\Catalog\ProductFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'style_code',
        'short_description',
        'description',
        'primary_image_url',
        'image_alt',
        'image_gallery',
        'base_price',
        'compare_at_price',
        'rating_average',
        'review_count',
        'status',
        'is_featured',
        'force_new_badge',
        'featured_rank',
        'track_inventory',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'rating_average' => 'decimal:1',
            'is_featured' => 'boolean',
            'force_new_badge' => 'boolean',
            'image_gallery' => 'array',
            'featured_rank' => 'integer',
            'primary_image_updated_at' => 'datetime',
            'track_inventory' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product): void {
            if (! $product->isDirty('primary_image_url')) {
                return;
            }

            $product->primary_image_updated_at = filled($product->primary_image_url)
                || filled($product->getOriginal('primary_image_url'))
                ? now()
                : null;
        });

        static::saved(function (Product $product): void {
            if (! $product->wasChanged('primary_image_url') || ! self::visualSearchIndexTableExists()) {
                return;
            }

            VisualSearchIndexEntry::query()
                ->where('product_id', $product->id)
                ->update([
                    'source_updated_at' => $product->primary_image_updated_at ?? now(),
                ]);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function visibleReviews(): HasMany
    {
        return $this->reviews()->where('is_visible', true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    protected function isNewArrival(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->created_at !== null
                && $this->created_at->greaterThanOrEqualTo(now()->subDays((int) config('storefront.catalog.new_badge_window_days', 60))),
        );
    }

    protected function showsNewBadge(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => (bool) $this->force_new_badge || $this->is_new_arrival,
        );
    }

    protected function showsSaleBadge(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->compare_at_price !== null
                && (float) $this->compare_at_price > (float) $this->base_price,
        );
    }

    protected function hasReviews(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => (int) $this->review_count > 0,
        );
    }

    protected function showsRatingSummary(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->has_reviews,
        );
    }

    protected function ratingDisplayState(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->has_reviews ? 'rated' : 'no_reviews',
        );
    }

    private static function visualSearchIndexTableExists(): bool
    {
        try {
            return Schema::hasTable('visual_search_index_entries');
        } catch (\Throwable) {
            return false;
        }
    }
}
