<?php

namespace App\Support\Storefront;

use App\Models\Catalog\Product;

class ProductMediaResolver
{
    public function __construct(
        private readonly ProductMediaPath $mediaPath,
    ) {}

    public function imageUrlFor(?Product $product): ?string
    {
        if (! $product) {
            return null;
        }

        return $this->normalizeUrl($product->primary_image_url)
            ?? $this->galleryFor($product)[0]
            ?? null;
    }

    public function galleryFor(?Product $product): array
    {
        if (! $product) {
            return [];
        }

        return collect($product->image_gallery ?? [])
            ->map(fn (mixed $url): ?string => $this->normalizeUrl($url))
            ->filter()
            ->values()
            ->all();
    }

    public function altTextFor(?Product $product, ?string $fallbackTitle = null): string
    {
        if ($product && filled($product->image_alt)) {
            return trim($product->image_alt);
        }

        $title = $fallbackTitle ?? $product?->name ?? 'Ysabelle Retail footwear';

        return "{$title} by Ysabelle Retail";
    }

    public function pathFor(?Product $product, string $variant = 'primary'): ?string
    {
        if (! $product) {
            return null;
        }

        if ($variant === 'primary') {
            return $this->mediaPath->toRelativePath($product->primary_image_url);
        }

        $galleryIndex = max(0, ((int) str_replace('gallery-', '', $variant)) - 1);

        return $this->mediaPath->toRelativePath($product->image_gallery[$galleryIndex] ?? null);
    }

    private function normalizeUrl(mixed $url): ?string
    {
        return $this->mediaPath->toUrl($url);
    }
}
