<?php

namespace App\Support\Catalog;

final class ProductPhotoAssetLocator
{
    private const PHOTO_EXTENSIONS = ['jpg', 'jpeg', 'webp'];

    public function primaryRelativePath(?string $categorySlug, string $productSlug): ?string
    {
        return $this->firstExistingRelativePath($categorySlug, $productSlug);
    }

    public function galleryRelativePaths(?string $categorySlug, string $productSlug): array
    {
        $gallery = [];

        for ($index = 1; $index <= 3; $index++) {
            $path = $this->firstExistingRelativePath($categorySlug, "{$productSlug}-gallery-{$index}");

            if ($path !== null) {
                $gallery[] = $path;
            }
        }

        return $gallery;
    }

    private function firstExistingRelativePath(?string $categorySlug, string $basename): ?string
    {
        $categorySlug = is_string($categorySlug) ? trim($categorySlug) : '';
        $basename = trim($basename);

        if ($categorySlug === '' || $basename === '') {
            return null;
        }

        foreach (self::PHOTO_EXTENSIONS as $extension) {
            $relativePath = "images/products/{$categorySlug}/{$basename}.{$extension}";

            if (is_file(public_path($relativePath))) {
                return $relativePath;
            }
        }

        return null;
    }
}
