<?php

namespace App\Services\Catalog;

use App\Models\Catalog\Product;
use App\Models\Storefront\VisualSearchIndexEntry;
use App\Services\Storefront\ImageFeatureExtractor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class CatalogImageAuditService
{
    public function __construct(
        private readonly ImageFeatureExtractor $featureExtractor,
    ) {}

    public function audit(): array
    {
        $products = Product::query()
            ->with('category:id,name,slug')
            ->orderBy('name')
            ->get([
                'id',
                'category_id',
                'name',
                'slug',
                'primary_image_url',
                'image_gallery',
            ]);

        $productRows = $products->map(fn (Product $product): array => [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'category' => $product->category?->name,
            'primary' => $this->normalizePrimary($product->primary_image_url),
            'gallery' => $this->normalizeGallery($product->image_gallery),
        ]);

        $totalProducts = $productRows->count();
        $productsWithPrimary = $productRows->filter(fn (array $row): bool => $row['primary']['raw'] !== null)->count();
        $productsMissingPrimary = $totalProducts - $productsWithPrimary;
        $productsWithGallery = $productRows->filter(fn (array $row): bool => $row['gallery'] !== [])->count();
        $galleryImageCount = $productRows->sum(fn (array $row): int => count($row['gallery']));

        $rawPrimaryUrls = $productRows
            ->pluck('primary.raw')
            ->filter()
            ->values();

        $normalizedPrimaryUrls = $productRows
            ->pluck('primary.normalized')
            ->filter()
            ->values();

        $galleryUrls = $productRows
            ->pluck('gallery')
            ->flatten(1)
            ->filter(fn (mixed $url): bool => is_string($url) && $url !== '')
            ->values();

        $catalogImageUrls = $rawPrimaryUrls
            ->concat($galleryUrls)
            ->filter()
            ->unique()
            ->values();

        $duplicates = [
            'exact_url' => $this->duplicateGroups($productRows, 'primary.raw'),
            'normalized_url' => $this->duplicateGroups($productRows, 'primary.normalized'),
            'local_path' => $this->duplicateGroups($productRows, 'primary.local_path'),
            'url_hash' => $this->duplicateGroups($productRows, 'primary.url_hash'),
            'perceptual_hash' => $this->perceptualHashGroups($productRows),
        ];

        $indexMetrics = $this->indexMetrics($catalogImageUrls);
        $primaryUniquenessRatio = $totalProducts === 0
            ? 1.0
            : round($normalizedPrimaryUrls->unique()->count() / max($totalProducts, 1), 4);

        $errors = [];
        $warnings = [];

        if ($productsMissingPrimary > 0) {
            $errors[] = "{$productsMissingPrimary} products are missing a primary image.";
        }

        if ($duplicates['normalized_url'] !== []) {
            $errors[] = count($duplicates['normalized_url']).' duplicate normalized primary image groups detected.';
        }

        if ($primaryUniquenessRatio < 0.95) {
            $errors[] = 'Primary image uniqueness ratio is below 95%.';
        }

        if (
            $indexMetrics['entries'] > 0
            && $indexMetrics['distinct_indexed_image_urls'] < max(1, (int) floor($totalProducts * 0.95))
            && ! $indexMetrics['stale']
        ) {
            $errors[] = 'Visual search index distinct image URL count is far below product count.';
        }

        if ($indexMetrics['entries'] > 0 && $indexMetrics['entries_with_embeddings'] < $indexMetrics['entries']) {
            $errors[] = 'Some indexed images are missing embeddings.';
        }

        if ($productsWithGallery < $totalProducts) {
            $warnings[] = ($totalProducts - $productsWithGallery).' products are missing gallery images.';
        }

        if ($galleryImageCount > 0 && ($galleryImageCount / max($totalProducts, 1)) < 2) {
            $warnings[] = 'Average gallery coverage is below 2 images per product.';
        }

        if ($indexMetrics['entries'] === 0) {
            $warnings[] = 'Visual search index is empty. Run php artisan visual-search:index --fresh.';
        } elseif ($indexMetrics['stale']) {
            $warnings[] = 'Visual search index is stale relative to the current catalog images. Run php artisan visual-search:index --fresh.';
        }

        $status = $errors !== [] ? 'red' : ($warnings !== [] ? 'warning' : 'green');

        return [
            'status' => $status,
            'products' => [
                'total' => $totalProducts,
                'with_primary' => $productsWithPrimary,
                'missing_primary' => $productsMissingPrimary,
                'with_gallery' => $productsWithGallery,
                'average_gallery_images' => round($galleryImageCount / max($totalProducts, 1), 2),
            ],
            'primary_images' => [
                'total_urls' => $rawPrimaryUrls->count(),
                'distinct_urls' => $rawPrimaryUrls->unique()->count(),
                'distinct_normalized_urls' => $normalizedPrimaryUrls->unique()->count(),
                'uniqueness_ratio' => $primaryUniquenessRatio,
            ],
            'gallery_images' => [
                'total_urls' => $galleryUrls->count(),
                'distinct_urls' => $galleryUrls->unique()->count(),
            ],
            'index' => $indexMetrics,
            'duplicates' => $duplicates,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    private function indexMetrics(Collection $catalogImageUrls): array
    {
        if (! $this->indexTableExists()) {
            return [
                'entries' => 0,
                'distinct_indexed_image_urls' => 0,
                'entries_with_embeddings' => 0,
                'fallback_only_entries' => 0,
                'stale' => false,
            ];
        }

        $entries = VisualSearchIndexEntry::query()->count();
        $entriesWithEmbeddings = VisualSearchIndexEntry::query()->whereNotNull('embedding_vector')->count();
        $indexedUrls = VisualSearchIndexEntry::query()
            ->pluck('image_url')
            ->filter(fn (mixed $url): bool => is_string($url) && $url !== '')
            ->unique()
            ->values();

        $catalogUrlSet = $catalogImageUrls->flip();
        $indexedUrlSet = $indexedUrls->flip();
        $missingCatalogUrls = $catalogImageUrls->reject(fn (string $url): bool => $indexedUrlSet->has($url))->count();
        $orphanedIndexedUrls = $indexedUrls->reject(fn (string $url): bool => $catalogUrlSet->has($url))->count();

        return [
            'entries' => $entries,
            'distinct_indexed_image_urls' => $indexedUrls->count(),
            'entries_with_embeddings' => $entriesWithEmbeddings,
            'fallback_only_entries' => max(0, $entries - $entriesWithEmbeddings),
            'stale' => $entries > 0 && ($missingCatalogUrls > 0 || $orphanedIndexedUrls > 0),
        ];
    }

    private function duplicateGroups(Collection $rows, string $field): array
    {
        return $rows
            ->filter(fn (array $row): bool => filled(data_get($row, $field)))
            ->groupBy(fn (array $row): string => (string) data_get($row, $field))
            ->filter(fn (Collection $group): bool => $group->count() > 1)
            ->map(function (Collection $group, string $value): array {
                return [
                    'value' => $value,
                    'count' => $group->count(),
                    'products' => $group
                        ->map(fn (array $row): array => [
                            'name' => $row['name'],
                            'slug' => $row['slug'],
                            'category' => $row['category'],
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function perceptualHashGroups(Collection $rows): array
    {
        $hashes = $rows
            ->map(function (array $row): ?array {
                $path = $row['primary']['local_path'] ?? null;

                if (! is_string($path) || ! is_file($path)) {
                    return null;
                }

                $binary = @file_get_contents($path);
                $features = is_string($binary) ? $this->featureExtractor->extractFromBinary($binary) : null;

                if (! is_array($features) || empty($features['perceptual_hash'])) {
                    return null;
                }

                return $row + ['perceptual_hash' => $features['perceptual_hash']];
            })
            ->filter();

        return $hashes
            ->groupBy('perceptual_hash')
            ->filter(fn (Collection $group): bool => $group->count() > 1)
            ->map(function (Collection $group, string $hash): array {
                return [
                    'value' => $hash,
                    'count' => $group->count(),
                    'products' => $group
                        ->map(fn (array $row): array => [
                            'name' => $row['name'],
                            'slug' => $row['slug'],
                            'category' => $row['category'],
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function normalizePrimary(mixed $url): array
    {
        $raw = $this->normalizeUrlString($url);
        $normalized = $this->normalizedIdentityUrl($raw);
        $localPath = $this->localPublicPath($normalized ?? $raw);

        return [
            'raw' => $raw,
            'normalized' => $normalized,
            'local_path' => $localPath,
            'url_hash' => $normalized ? hash('sha256', $normalized) : null,
        ];
    }

    private function normalizeGallery(mixed $gallery): array
    {
        if (! is_array($gallery)) {
            return [];
        }

        return collect($gallery)
            ->map(fn (mixed $url): ?string => $this->normalizedIdentityUrl($this->normalizeUrlString($url)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeUrlString(mixed $url): ?string
    {
        if (! is_string($url)) {
            return null;
        }

        $url = trim($url);

        return $url !== '' ? $url : null;
    }

    private function normalizedIdentityUrl(?string $url): ?string
    {
        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'], $parts['path'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        $path = rtrim($parts['path'], '/');

        if ($path === '') {
            $path = '/';
        }

        return "{$scheme}://{$host}{$path}";
    }

    private function localPublicPath(?string $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        $parsedHost = parse_url($url, PHP_URL_HOST);
        $parsedPath = parse_url($url, PHP_URL_PATH);
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (! is_string($parsedPath) || $parsedPath === '') {
            return null;
        }

        $allowedHosts = array_filter([
            is_string($appHost) ? strtolower($appHost) : null,
            '127.0.0.1',
            'localhost',
        ]);

        if ($parsedHost !== null && ! in_array(strtolower((string) $parsedHost), $allowedHosts, true)) {
            return null;
        }

        $candidate = public_path(ltrim($parsedPath, '/'));

        return is_file($candidate) ? $candidate : null;
    }

    private function indexTableExists(): bool
    {
        try {
            return Schema::hasTable('visual_search_index_entries');
        } catch (\Throwable) {
            return false;
        }
    }
}
