<?php

namespace App\Support\Storefront;

use App\Models\Catalog\Product;

class ProductMediaResolver
{
    public function pathFor(Product|string $product, string $variant = 'card'): string
    {
        $slug = $product instanceof Product ? $product->slug : $product;
        $media = config("storefront.product_media.{$slug}");

        if ($media && isset($media[$variant])) {
            return asset($media[$variant]);
        }

        if ($media && isset($media['card'])) {
            return asset($media['card']);
        }

        return asset('/images/storefront/products/aurum-card.png');
    }
}
