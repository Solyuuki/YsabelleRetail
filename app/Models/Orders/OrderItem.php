<?php

namespace App\Models\Orders;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductReview;
use App\Models\Catalog\ProductVariant;
use App\Support\Storefront\ProductMediaPath;
use App\Support\Storefront\ProductMediaResolver;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'product_name',
        'variant_name',
        'sku',
        'quantity',
        'unit_price',
        'line_total',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function review(): HasOne
    {
        return $this->hasOne(ProductReview::class);
    }

    protected function productImageUrl(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                $snapshotUrl = app(ProductMediaPath::class)->toUrl(data_get($this->metadata, 'product_image_url'));

                if ($snapshotUrl) {
                    return $snapshotUrl;
                }

                return app(ProductMediaResolver::class)->imageUrlFor($this->resolvedProduct());
            },
        );
    }

    protected function productImageAlt(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $snapshotAlt = trim((string) data_get($this->metadata, 'product_image_alt', ''));

                if ($snapshotAlt !== '') {
                    return $snapshotAlt;
                }

                $product = $this->resolvedProduct();

                return app(ProductMediaResolver::class)->altTextFor(
                    $product,
                    $this->product_name ?: $product?->name,
                );
            },
        );
    }

    private function resolvedProduct(): ?Product
    {
        if ($this->relationLoaded('product') && $this->product instanceof Product) {
            return $this->product;
        }

        if ($this->product instanceof Product) {
            return $this->product;
        }

        $variant = $this->relationLoaded('variant') ? $this->variant : $this->variant()->with('product')->first();

        if (! $variant instanceof ProductVariant) {
            return null;
        }

        if ($variant->relationLoaded('product') && $variant->product instanceof Product) {
            return $variant->product;
        }

        return $variant->product;
    }
}
