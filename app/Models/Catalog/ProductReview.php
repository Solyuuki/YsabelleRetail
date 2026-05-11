<?php

namespace App\Models\Catalog;

use App\Models\Orders\OrderItem;
use App\Models\User;
use Database\Factories\Catalog\ProductReviewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReview extends Model
{
    /** @use HasFactory<ProductReviewFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'user_id',
        'order_item_id',
        'rating',
        'title',
        'body',
        'is_verified_purchase',
        'is_visible',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_verified_purchase' => 'boolean',
            'is_visible' => 'boolean',
        ];
    }

    protected static function newFactory(): ProductReviewFactory
    {
        return ProductReviewFactory::new();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
