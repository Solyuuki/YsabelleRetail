<?php

namespace App\Services\Storefront;

use App\Models\Catalog\Product;
use App\Models\Storefront\VisualSearchIndexEntry;
use App\Support\Storefront\ProductMediaResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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

        if (app()->environment('testing') || $this->shouldBypassCache()) {
            return $this->queryIndexedEntries();
        }

        return Cache::rememberForever($this->cacheKey(), fn (): Collection => $this->queryIndexedEntries());
    }

    public function rebuildIndex(bool $fresh = false, ?int $limit = null): array
    {
        if (! $this->indexTableExists()) {
            return $this->emptyStats();
        }

        $stats = $this->baseStats();
        $stats['index_entries_before'] = VisualSearchIndexEntry::query()->count();
        $stats['entries_with_embeddings_before'] = VisualSearchIndexEntry::query()->whereNotNull('embedding_vector')->count();
        $discoveredKeys = [];
        $successfulKeys = [];
        $preparedEntries = [];
        $query = Product::query()
            ->with(['category', 'variants.inventoryItem'])
            ->where('status', 'active');

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        $this->writeProgress(sprintf(
            'Scanning active products%s...',
            $limit !== null && $limit > 0 ? " (limit {$limit})" : ' in batches of 25',
        ));

        if ($limit !== null && $limit > 0) {
            $this->prepareEntriesFromProducts($query->get(), $stats, $discoveredKeys, $preparedEntries);
        } else {
            $query->chunkById(25, function (Collection $products) use (&$stats, &$discoveredKeys, &$preparedEntries): void {
                $this->prepareEntriesFromProducts($products, $stats, $discoveredKeys, $preparedEntries);
            });
        }

        $embeddingResults = $this->embedPreparedEntries($preparedEntries, $stats);
        $this->writeProgress(sprintf('Persisting %d prepared image entries...', count($preparedEntries)));

        foreach ($preparedEntries as $entry) {
            try {
                $embedding = $embeddingResults[$entry['id']] ?? null;
                $embeddingOk = is_array($embedding)
                    && ($embedding['ok'] ?? false) === true
                    && is_array($embedding['embedding'] ?? null);

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
                        'source_checksum' => $entry['source_checksum'],
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

                $successfulKeys[$this->entryKey($entry['product']->id, $entry['image_url'])] = true;
                $stats['images_indexed']++;
            } finally {
                if ($entry['temporary_path'] && is_string($entry['materialized_path'])) {
                    @unlink($entry['materialized_path']);
                }
            }

            if ($stats['images_indexed'] % 25 === 0 || $stats['images_indexed'] === count($preparedEntries)) {
                $this->writeProgress(sprintf(
                    'Persisted %d/%d image entries.',
                    $stats['images_indexed'],
                    max(count($preparedEntries), 1),
                ));
            }
        }

        $shouldDeleteStaleEntries = ! ($fresh && $stats['images_indexed'] === 0 && $stats['index_entries_before'] > 0);

        if (! $shouldDeleteStaleEntries) {
            Log::error('visual-search.index.refresh_preserved_previous_index', [
                'index_entries_before' => $stats['index_entries_before'],
                'products_scanned' => $stats['products_scanned'],
                'images_skipped' => $stats['images_skipped'],
                'missing_image_files' => $stats['missing_image_files'],
            ]);
        } else {
            $staleIds = VisualSearchIndexEntry::query()
                ->get(['id', 'product_id', 'image_url'])
                ->reject(function (VisualSearchIndexEntry $entry) use ($fresh, $discoveredKeys, $successfulKeys): bool {
                    $key = $this->entryKey($entry->product_id, $entry->image_url);

                    return $fresh
                        ? isset($successfulKeys[$key])
                        : isset($discoveredKeys[$key]);
                })
                ->pluck('id')
                ->values();

            foreach ($staleIds->chunk(100) as $chunk) {
                $stats['entries_deleted'] += VisualSearchIndexEntry::query()->whereIn('id', $chunk)->delete();
            }
        }

        Cache::forget($this->cacheKey());
        $stats['index_entries_after'] = VisualSearchIndexEntry::query()->count();
        $stats['entries_with_embeddings_after'] = VisualSearchIndexEntry::query()->whereNotNull('embedding_vector')->count();

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
                'gd_available' => $this->featureExtractor->available(),
                'gd_message' => $this->featureExtractor->available()
                    ? 'GD image support is available.'
                    : 'GD image support is unavailable.',
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
            'gd_available' => $this->featureExtractor->available(),
            'gd_message' => $this->featureExtractor->available()
                ? 'GD image support is available.'
                : 'GD image support is unavailable.',
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
            $entry['source_checksum'],
            $entry['features']['feature_version'] ?? 'v1',
            $embeddingOk ? $this->embeddingService->model() : 'fallback',
            $embeddingOk ? $this->embeddingService->embeddingVersion() : 'fallback',
        ]));
    }

    private function baseStats(): array
    {
        return [
            'products_scanned' => 0,
            'products_with_images' => 0,
            'products_skipped' => 0,
            'images_discovered' => 0,
            'images_indexed' => 0,
            'images_skipped' => 0,
            'missing_image_files' => 0,
            'entries_deleted' => 0,
            'embeddings_generated' => 0,
            'embeddings_failed' => 0,
            'index_entries_before' => 0,
            'entries_with_embeddings_before' => 0,
            'index_entries_after' => 0,
            'entries_with_embeddings_after' => 0,
        ];
    }

    private function emptyStats(): array
    {
        return $this->baseStats();
    }

    private function prepareEntriesFromProducts(Collection $products, array &$stats, array &$discoveredKeys, array &$preparedEntries): void
    {
        foreach ($products as $product) {
            $stats['products_scanned']++;

            $imageUrls = collect([$this->productMedia->imageUrlFor($product)])
                ->concat($this->productMedia->galleryFor($product))
                ->filter()
                ->unique()
                ->values();

            if ($imageUrls->isEmpty()) {
                $stats['products_skipped']++;

                continue;
            }

            $stats['products_with_images']++;
            $productPreparedImages = 0;

            foreach ($imageUrls as $index => $imageUrl) {
                $entryKey = $this->entryKey($product->id, $imageUrl);
                $discoveredKeys[$entryKey] = true;
                $stats['images_discovered']++;

                $materialized = $this->imageSource->materializeFromUrl($imageUrl);

                if (! is_array($materialized) || ! is_string($materialized['path'] ?? null) || $materialized['path'] === '') {
                    $stats['images_skipped']++;
                    $stats['missing_image_files']++;
                    $this->logIndexIssue('image_missing', [
                        'product_id' => $product->id,
                        'image_url' => $imageUrl,
                    ]);

                    continue;
                }

                $binary = @file_get_contents($materialized['path']);

                if (! is_string($binary) || $binary === '') {
                    $stats['images_skipped']++;
                    $this->logIndexIssue('image_unreadable', [
                        'product_id' => $product->id,
                        'image_url' => $imageUrl,
                        'path' => $materialized['path'],
                    ]);

                    if (($materialized['temporary'] ?? false) === true) {
                        @unlink($materialized['path']);
                    }

                    continue;
                }

                $features = $this->featureExtractor->extractFromBinary($binary);

                if (! is_array($features)) {
                    $stats['images_skipped']++;
                    $this->logIndexIssue('feature_extraction_failed', [
                        'product_id' => $product->id,
                        'image_url' => $imageUrl,
                        'path' => $materialized['path'],
                    ]);

                    if (($materialized['temporary'] ?? false) === true) {
                        @unlink($materialized['path']);
                    }

                    continue;
                }

                $preparedEntries[] = [
                    'id' => $product->id.'-'.$index.'-'.substr(hash('sha256', $imageUrl), 0, 16),
                    'product' => $product,
                    'image_url' => $imageUrl,
                    'image_path' => ($materialized['temporary'] ?? false) === false
                        ? ($materialized['relative_path'] ?? $materialized['path'])
                        : null,
                    'materialized_path' => $materialized['path'],
                    'temporary_path' => (bool) ($materialized['temporary'] ?? false),
                    'source_checksum' => hash('sha256', $binary),
                    'features' => $features,
                    'image_index' => $index,
                ];
                $productPreparedImages++;
            }

            if ($productPreparedImages === 0) {
                $stats['products_skipped']++;
            }
        }

        if ($products->isNotEmpty()) {
            $this->writeProgress(sprintf(
                'Scanned %d products so far, discovered %d images, prepared %d entries.',
                $stats['products_scanned'],
                $stats['images_discovered'],
                count($preparedEntries),
            ));
        }
    }

    private function embedPreparedEntries(array $preparedEntries, array &$stats): array
    {
        if ($preparedEntries === [] || ! $this->embeddingService->enabled()) {
            return [];
        }

        $embeddingResults = [];
        $chunks = array_chunk($preparedEntries, 128);
        $chunkCount = count($chunks);

        foreach ($chunks as $chunkIndex => $chunk) {
            $inputs = collect($chunk)
                ->filter(fn (array $entry): bool => is_string($entry['materialized_path']) && $entry['materialized_path'] !== '')
                ->map(fn (array $entry): array => [
                    'id' => $entry['id'],
                    'path' => $entry['materialized_path'],
                ])
                ->values()
                ->all();

            if ($inputs === []) {
                continue;
            }

            $this->writeProgress(sprintf(
                'Embedding batch %d/%d (%d images)...',
                $chunkIndex + 1,
                max($chunkCount, 1),
                count($inputs),
            ));

            try {
                $chunkResults = $this->embeddingService->embedPaths($inputs);
            } catch (\Throwable $exception) {
                $stats['embeddings_failed'] += count($inputs);
                Log::error('visual-search.index.embedding_batch_failed', [
                    'input_count' => count($inputs),
                    'message' => $exception->getMessage(),
                ]);

                continue;
            }

            $resultIds = [];

            foreach ($chunkResults as $id => $result) {
                $resultIds[(string) $id] = true;
                $embeddingResults[(string) $id] = $result;
                $embeddingOk = is_array($result)
                    && ($result['ok'] ?? false) === true
                    && is_array($result['embedding'] ?? null);

                if ($embeddingOk) {
                    $stats['embeddings_generated']++;

                    continue;
                }

                $stats['embeddings_failed']++;
                Log::warning('visual-search.index.embedding_item_failed', [
                    'entry_id' => $id,
                    'error' => is_array($result) ? ($result['error'] ?? 'unknown_embedding_error') : 'missing_embedding_result',
                ]);
            }

            foreach ($inputs as $input) {
                if (! isset($resultIds[$input['id']])) {
                    $stats['embeddings_failed']++;
                    Log::warning('visual-search.index.embedding_item_missing', [
                        'entry_id' => $input['id'],
                        'path' => $input['path'],
                    ]);
                }
            }

            $this->writeProgress(sprintf(
                'Completed embedding batch %d/%d. Generated: %d, Failed: %d.',
                $chunkIndex + 1,
                max($chunkCount, 1),
                $stats['embeddings_generated'],
                $stats['embeddings_failed'],
            ));
        }

        return $embeddingResults;
    }

    private function logIndexIssue(string $event, array $context): void
    {
        Log::warning('visual-search.index.'.$event, $context);
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

    private function shouldBypassCache(): bool
    {
        return in_array((string) config('cache.default', 'database'), ['database', 'dynamodb'], true);
    }

    private function writeProgress(string $message): void
    {
        Log::info('visual-search.index.progress', ['message' => $message]);

        if (! app()->runningInConsole()) {
            return;
        }

        $stream = defined('STDOUT') ? STDOUT : fopen('php://stdout', 'w');

        if (is_resource($stream)) {
            fwrite($stream, '[visual-search] '.$message.PHP_EOL);
        }
    }
}
