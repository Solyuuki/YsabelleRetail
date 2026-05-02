<?php

namespace App\Services\Catalog;

use App\Models\Catalog\Product;
use App\Support\Catalog\ProductPhotoAssetLocator;
use Illuminate\Support\Collection;

class CatalogProductImageSyncService
{
    public function __construct(
        private readonly ProductPhotoAssetLocator $photoAssetLocator,
    ) {}

    public function sync(bool $persist = true): array
    {
        $stats = [
            'products_scanned' => 0,
            'products_updated' => 0,
            'images_copied' => 0,
            'images_already_present' => 0,
            'missing_sources' => [],
        ];

        Product::query()
            ->with('category:id,slug,name')
            ->orderBy('id')
            ->chunkById(25, function (Collection $products) use ($persist, &$stats): void {
                foreach ($products as $product) {
                    $stats['products_scanned']++;

                    $normalized = $this->syncProduct($product, $persist, $stats);

                    if ($normalized['changed'] && $persist) {
                        $stats['products_updated']++;
                    }
                }
            });

        return $stats;
    }

    private function syncProduct(Product $product, bool $persist, array &$stats): array
    {
        $primaryRelativePath = $this->photoAssetLocator->primaryRelativePath($product->category?->slug, $product->slug);

        if (! is_string($primaryRelativePath) || $primaryRelativePath === '') {
            $stats['missing_sources'][] = [
                'product' => $product->name,
                'variant' => 'primary',
                'source' => $product->primary_image_url,
            ];

            return ['changed' => false];
        }

        $newPrimary = $primaryRelativePath;
        $newGallery = $this->photoAssetLocator->galleryRelativePaths($product->category?->slug, $product->slug);
        $stats['images_already_present'] += 1 + count($newGallery);

        $changed = $product->primary_image_url !== $newPrimary || ($product->image_gallery ?? []) !== $newGallery;

        if ($changed && $persist) {
            $product->forceFill([
                'primary_image_url' => $newPrimary,
                'image_gallery' => $newGallery,
            ])->save();
        }

        return ['changed' => $changed];
    }
}
