<?php

namespace App\Services\Storefront;

use App\Models\Catalog\Product;
use App\Models\Storefront\VisualSearchIndexEntry;
use App\Support\Storefront\ProductMediaResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class VisualSearchIndexService
{
    public function __construct(
        private readonly ImageFeatureExtractor $featureExtractor,
        private readonly ProductMediaResolver $productMedia,
        private readonly VisualSearchImageSource $imageSource,
        private readonly VisualSearchEmbeddingService $embeddingService,
    ) {}

    public function indexedEntries(): Collection
    {
        if (! $this->indexTableExists()) {
            return collect();
        }

        if (app()->environment('testing')) {
            return $this->queryIndexedEntries();
        }

        return Cache::rememberForever($this->cacheKey(), fn (): Collection => $this->queryIndexedEntries());
    }

    public function rebuildIndex(bool $fresh = false): array
    {
        if (! $this->indexTableExists()) {
            return [
                'products_scanned' => 0,
                'images_indexed' => 0,
                'images_skipped' => 0,
                'entries_deleted' => 0,
                'embeddings_generated' => 0,
                'embeddings_failed' => 0,
            ];
        }

        if ($fresh) {
            VisualSearchIndexEntry::query()->delete();
        }

        $stats = [
            'products_scanned' => 0,
            'images_indexed' => 0,
            'images_skipped' => 0,
            'entries_deleted' => 0,
            'embeddings_generated' => 0,
            'embeddings_failed' => 0,
        ];

        $validKeys = [];

        Product::query()
            ->with(['category', 'variants.inventoryItem'])
            ->where('status', 'active')
            ->chunkById(25, function (Collection $products) use (&$stats, &$validKeys): void {
                $preparedEntries = [];

                foreach ($products as $product) {
                    $stats['products_scanned']++;

                    $imageUrls = collect([$this->productMedia->imageUrlFor($product)])
                        ->concat($this->productMedia->galleryFor($product))
                        ->filter()
                        ->unique()
                        ->values();

                    if ($imageUrls->isEmpty()) {
                        continue;
                    }

                    foreach ($imageUrls as $index => $imageUrl) {
                        $validKeys[$this->entryKey($product->id, $imageUrl)] = true;
                        $binary = $this->imageSource->loadFromUrl($imageUrl);

                        if (! is_string($binary) || $binary === '') {
                            $stats['images_skipped']++;

                            continue;
                        }

                        $features = $this->featureExtractor->extractFromBinary($binary);

                        if (! is_array($features)) {
                            $stats['images_skipped']++;

                            continue;
                        }

                        $materialized = $this->imageSource->materializeFromUrl($imageUrl);
                        $preparedEntries[] = [
                            'id' => $product->id.'-'.$index.'-'.substr(hash('sha256', $imageUrl), 0, 16),
                            'product' => $product,
                            'image_url' => $imageUrl,
                            'image_path' => is_array($materialized) && ($materialized['temporary'] ?? false) === false
                                ? $materialized['path']
                                : null,
                            'materialized_path' => is_array($materialized) ? $materialized['path'] : null,
                            'temporary_path' => is_array($materialized) ? (bool) ($materialized['temporary'] ?? false) : false,
                            'binary' => $binary,
                            'features' => $features,
                            'image_index' => $index,
                        ];
                    }
                }

                $embeddingInputs = collect($preparedEntries)
                    ->filter(fn (array $entry): bool => is_string($entry['materialized_path']) && $entry['materialized_path'] !== '')
                    ->map(fn (array $entry): array => [
                        'id' => $entry['id'],
                        'path' => $entry['materialized_path'],
                    ])
                    ->values()
                    ->all();

                $embeddingBatchFailed = false;

                try {
                    $embeddingResults = $this->embeddingService->embedPaths($embeddingInputs);
                } catch (\Throwable) {
                    $embeddingResults = [];
                    $embeddingBatchFailed = true;

                    if ($this->embeddingService->enabled()) {
                        $stats['embeddings_failed'] += count($embeddingInputs);
                    }
                }

                foreach ($preparedEntries as $entry) {
                    try {
                        $embedding = $embeddingResults[$entry['id']] ?? null;
                        $embeddingOk = is_array($embedding)
                            && ($embedding['ok'] ?? false) === true
                            && is_array($embedding['embedding'] ?? null);

                        if ($embeddingOk) {
                            $stats['embeddings_generated']++;
                        } elseif ($this->embeddingService->enabled() && ! $embeddingBatchFailed) {
                            $stats['embeddings_failed']++;
                        }

                        VisualSearchIndexEntry::query()->updateOrCreate(
                            [
                                'product_id' => $entry['product']->id,
                                'image_url_hash' => hash('sha256', $entry['image_url']),
                            ],
                            [
                                'image_url' => $entry['image_url'],
                                'image_path' => $entry['image_path'],
                                'product_variant_id' => $entry['product']->variants->first()?->id,
                                'image_role' => $entry['image_index'] === 0 ? 'primary' : 'gallery',
                                'feature_version' => $entry['features']['feature_version'],
                                'source_checksum' => hash('sha256', $entry['binary']),
                                'perceptual_hash' => $entry['features']['perceptual_hash'],
                                'color_histogram' => $entry['features']['color_histogram'],
                                'shape_profile_x' => $entry['features']['shape_profile_x'],
                                'shape_profile_y' => $entry['features']['shape_profile_y'],
                                'dominant_colors' => $entry['features']['dominant_colors'],
                                'mean_red' => $entry['features']['mean_red'],
                                'mean_green' => $entry['features']['mean_green'],
                                'mean_blue' => $entry['features']['mean_blue'],
                                'edge_density' => $entry['features']['edge_density'],
                                'foreground_ratio' => $entry['features']['foreground_ratio'],
                                'aspect_ratio' => $entry['features']['aspect_ratio'],
                                'width' => $entry['features']['width'],
                                'height' => $entry['features']['height'],
                                'embedding_vector' => $embeddingOk ? $embedding['embedding'] : null,
                                'embedding_crops' => $embeddingOk ? ($embedding['crop_embeddings'] ?? null) : null,
                                'embedding_model' => $embeddingOk ? $this->embeddingService->model() : null,
                                'embedding_version' => $embeddingOk ? $this->embeddingService->embeddingVersion() : null,
                                'index_version_key' => $this->indexVersionKey($entry, $embeddingOk),
                                'shoe_confidence' => $embeddingOk ? ($embedding['shoe_probability'] ?? null) : null,
                                'blur_score' => $embeddingOk ? data_get($embedding, 'metadata.blur_score') : null,
                                'embedding_generated_at' => $embeddingOk ? now() : null,
                                'source_updated_at' => $entry['product']->updated_at,
                                'indexed_at' => now(),
                            ],
                        );

                        $stats['images_indexed']++;
                    } finally {
                        if ($entry['temporary_path'] && is_string($entry['materialized_path'])) {
                            @unlink($entry['materialized_path']);
                        }
                    }
                }
            });

        $staleIds = VisualSearchIndexEntry::query()
            ->get(['id', 'product_id', 'image_url'])
            ->reject(fn (VisualSearchIndexEntry $entry): bool => isset($validKeys[$this->entryKey($entry->product_id, $entry->image_url)]))
            ->pluck('id')
            ->values();

        foreach ($staleIds->chunk(100) as $chunk) {
            $stats['entries_deleted'] += VisualSearchIndexEntry::query()->whereIn('id', $chunk)->delete();
        }

        Cache::forget($this->cacheKey());

        return $stats;
    }

    public function clearIndex(): int
    {
        if (! $this->indexTableExists()) {
            return 0;
        }

        $deleted = VisualSearchIndexEntry::query()->delete();
        Cache::forget($this->cacheKey());

        return $deleted;
    }

    public function health(): array
    {
        if (! $this->indexTableExists()) {
            return [
                'table_exists' => false,
                'entries' => 0,
                'embedded_entries' => 0,
                'fallback_only_entries' => 0,
            ];
        }

        $entries = VisualSearchIndexEntry::query()->count();
        $embeddedEntries = VisualSearchIndexEntry::query()->whereNotNull('embedding_vector')->count();
        $currentModel = $this->embeddingService->model();
        $currentVersion = $this->embeddingService->embeddingVersion();
        $outdatedEmbeddedEntries = VisualSearchIndexEntry::query()
            ->whereNotNull('embedding_vector')
            ->where(function ($query) use ($currentModel, $currentVersion): void {
                $query
                    ->where('embedding_model', '!=', $currentModel)
                    ->orWhere('embedding_version', '!=', $currentVersion);
            })
            ->count();
        $staleSourceEntries = VisualSearchIndexEntry::query()
            ->whereColumn('source_updated_at', '>', 'indexed_at')
            ->count();

        return [
            'table_exists' => true,
            'entries' => $entries,
            'embedded_entries' => $embeddedEntries,
            'fallback_only_entries' => max(0, $entries - $embeddedEntries),
            'current_model' => $currentModel,
            'current_embedding_version' => $currentVersion,
            'outdated_embedded_entries' => $outdatedEmbeddedEntries,
            'stale_source_entries' => $staleSourceEntries,
        ];
    }

    private function entryKey(int $productId, string $imageUrl): string
    {
        return $productId.'|'.$imageUrl;
    }

    private function indexVersionKey(array $entry, bool $embeddingOk): string
    {
        return hash('sha256', implode('|', [
            hash('sha256', $entry['binary']),
            $entry['features']['feature_version'] ?? 'v1',
            $embeddingOk ? $this->embeddingService->model() : 'fallback',
            $embeddingOk ? $this->embeddingService->embeddingVersion() : 'fallback',
        ]));
    }

    private function indexTableExists(): bool
    {
        try {
            return Schema::hasTable('visual_search_index_entries');
        } catch (\Throwable) {
            return false;
        }
    }

    private function queryIndexedEntries(): Collection
    {
        $query = VisualSearchIndexEntry::query()
            ->with(['product.category', 'product.variants.inventoryItem', 'variant'])
            ->whereHas('product', fn ($query) => $query->where('status', 'active'));

        if ($this->embeddingService->enabled()) {
            $model = $this->embeddingService->model();
            $version = $this->embeddingService->embeddingVersion();

            $query->where(function ($query) use ($model, $version): void {
                $query
                    ->whereNull('embedding_vector')
                    ->orWhere(function ($embeddedQuery) use ($model, $version): void {
                        $embeddedQuery
                            ->where('embedding_model', $model)
                            ->where('embedding_version', $version);
                    });
            });
        }

        return $query->get();
    }

    private function cacheKey(): string
    {
        return 'storefront.visual-search.index.'.hash('sha256', implode('|', [
            $this->embeddingService->enabled() ? 'embedding' : 'fallback',
            $this->embeddingService->model(),
            $this->embeddingService->embeddingVersion(),
        ]));
    }
}
