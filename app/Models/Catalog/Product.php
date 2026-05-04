<?php

namespace App\Models\Catalog;

use App\Models\Orders\OrderItem;
use Database\Factories\Catalog\ProductFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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

    public function markAsStorefrontNewArrival(bool $value = true): static
    {
        $this->setAttribute('storefront_new_arrival', $value);

        return $this;
    }

    protected function storefrontNewArrival(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value): bool => (bool) ($value ?? false),
        );
    }

    protected function showsNewBadge(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => (bool) ($this->storefront_new_arrival || $this->is_featured),
        );
    }

    protected function showsSaleBadge(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->compare_at_price !== null
                && (float) $this->compare_at_price > (float) $this->base_price,
        );
    }
}
