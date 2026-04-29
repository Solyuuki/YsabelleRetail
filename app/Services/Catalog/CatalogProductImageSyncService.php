<?php

namespace App\Services\Catalog;

use App\Models\Catalog\Product;
use App\Support\Storefront\ProductMediaPath;
use Illuminate\Support\Collection;

class CatalogProductImageSyncService
{
    public function __construct(
        private readonly ProductMediaPath $mediaPath,
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
        $primarySource = $this->resolveSourcePath($product->primary_image_url)
            ?? $this->legacySourcePath($product, 'primary');

        if (! is_string($primarySource) || ! is_file($primarySource)) {
            $stats['missing_sources'][] = [
                'product' => $product->name,
                'variant' => 'primary',
                'source' => $product->primary_image_url,
            ];

            return ['changed' => false];
        }

        $primaryRelativePath = $this->targetRelativePath($product, 'primary', pathinfo($primarySource, PATHINFO_EXTENSION) ?: 'png');
        $this->copyIntoProductsDirectory($primarySource, $primaryRelativePath, $stats);

        $gallery = [];
        $storedGallery = is_array($product->image_gallery) ? array_values($product->image_gallery) : [];

        for ($index = 0; $index < 3; $index++) {
            $variant = 'gallery-'.($index + 1);
            $gallerySource = $this->resolveSourcePath($storedGallery[$index] ?? null)
                ?? $this->legacySourcePath($product, $variant);

            if (! is_string($gallerySource) || ! is_file($gallerySource)) {
                $stats['missing_sources'][] = [
                    'product' => $product->name,
                    'variant' => $variant,
                    'source' => $storedGallery[$index] ?? null,
                ];

                continue;
            }

            $galleryRelativePath = $this->targetRelativePath($product, $variant, pathinfo($gallerySource, PATHINFO_EXTENSION) ?: 'png');
            $this->copyIntoProductsDirectory($gallerySource, $galleryRelativePath, $stats);
            $gallery[] = $galleryRelativePath;
        }

        $newPrimary = $primaryRelativePath;
        $newGallery = $gallery;

        $changed = $product->primary_image_url !== $newPrimary || ($product->image_gallery ?? []) !== $newGallery;

        if ($changed && $persist) {
            $product->forceFill([
                'primary_image_url' => $newPrimary,
                'image_gallery' => $newGallery,
            ])->save();
        }

        return ['changed' => $changed];
    }

    private function resolveSourcePath(mixed $value): ?string
    {
        return $this->mediaPath->toLocalPublicPath($value);
    }

    private function legacySourcePath(Product $product, string $variant): ?string
    {
        $relativePath = sprintf(
            'images/catalog/generated/v1/%s/%s-%s.png',
            $product->category?->slug,
            $product->slug,
            $variant,
        );

        $candidate = public_path($relativePath);

        return is_file($candidate) ? $candidate : null;
    }

    private function targetRelativePath(Product $product, string $variant, string $extension): string
    {
        $extension = strtolower($extension);
        $extension = $extension !== '' ? $extension : 'png';
        $prefix = sprintf('images/products/%s/%s', $product->category?->slug, $product->slug);

        if ($variant === 'primary') {
            return "{$prefix}.{$extension}";
        }

        return "{$prefix}-{$variant}.{$extension}";
    }

    private function copyIntoProductsDirectory(string $sourcePath, string $targetRelativePath, array &$stats): void
    {
        $targetPath = public_path($targetRelativePath);
        $directory = dirname($targetPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        if (is_file($targetPath)) {
            $stats['images_already_present']++;

            return;
        }

        copy($sourcePath, $targetPath);
        $stats['images_copied']++;
    }
}
