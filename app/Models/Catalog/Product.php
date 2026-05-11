<?php

namespace App\Models\Catalog;

use App\Models\Orders\OrderItem;
use Database\Factories\Catalog\ProductFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
            'image_gallery' => 'array',
            'featured_rank' => 'integer',
            'track_inventory' => 'boolean',
        ];
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
            get: fn (): bool => $this->is_new_arrival,
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
}
