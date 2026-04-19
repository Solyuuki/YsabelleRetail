<?php

namespace App\Models\Catalog;

use Database\Factories\Catalog\ProductFactory;
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
}
