<?php

namespace Database\Seeders\Catalog\Support;

use App\Support\Catalog\ProductPhotoAssetLocator;
use Illuminate\Support\Str;
use RuntimeException;

final class CatalogProductImageFactory
{
    public static function build(string $categorySlug, string $categoryName, array $product, array $colors): array
    {
        $productSlug = Str::slug((string) ($product['name'] ?? 'product'));
        $assetLocator = new ProductPhotoAssetLocator();
        $primaryImage = $assetLocator->primaryRelativePath($categorySlug, $productSlug);

        if ($primaryImage === null) {
            throw new RuntimeException("Missing real product photo for [{$categorySlug}/{$productSlug}].");
        }

        return [
            'primary_image_url' => $primaryImage,
            'image_alt' => sprintf('%s %s product image', $product['name'], $categoryName),
            'image_gallery' => $assetLocator->galleryRelativePaths($categorySlug, $productSlug),
        ];
    }
}
