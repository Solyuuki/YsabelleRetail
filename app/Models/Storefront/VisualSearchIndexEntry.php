<?php

namespace App\Models\Storefront;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisualSearchIndexEntry extends Model
{
    protected $fillable = [
        'product_id',
        'product_variant_id',
        'image_url',
        'image_path',
        'image_url_hash',
        'image_role',
        'feature_version',
        'source_checksum',
        'perceptual_hash',
        'color_histogram',
        'shape_profile_x',
        'shape_profile_y',
        'dominant_colors',
        'mean_red',
        'mean_green',
        'mean_blue',
        'edge_density',
        'foreground_ratio',
        'aspect_ratio',
        'width',
        'height',
        'embedding_vector',
        'embedding_crops',
        'embedding_model',
        'embedding_version',
        'index_version_key',
        'shoe_confidence',
        'blur_score',
        'embedding_generated_at',
        'source_updated_at',
        'indexed_at',
    ];

    protected function casts(): array
    {
        return [
            'color_histogram' => 'array',
            'shape_profile_x' => 'array',
            'shape_profile_y' => 'array',
            'dominant_colors' => 'array',
            'mean_red' => 'float',
            'mean_green' => 'float',
            'mean_blue' => 'float',
            'edge_density' => 'float',
            'foreground_ratio' => 'float',
            'aspect_ratio' => 'float',
            'embedding_vector' => 'array',
            'embedding_crops' => 'array',
            'shoe_confidence' => 'float',
            'blur_score' => 'float',
            'embedding_generated_at' => 'datetime',
            'source_updated_at' => 'datetime',
            'indexed_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
