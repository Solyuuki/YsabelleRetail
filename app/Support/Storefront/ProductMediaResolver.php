<?php

namespace App\Support\Storefront;

use App\Models\Catalog\Product;

class ProductMediaResolver
{
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
        return $this->imageUrlFor($product);
    }

    private function normalizeUrl(mixed $url): ?string
    {
        if (! is_string($url)) {
            return null;
        }

        $url = trim($url);

        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        return $url;
    }
}
