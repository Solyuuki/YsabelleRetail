<?php

namespace App\Services\Storefront;

use App\Models\Storefront\VisualSearchIndexEntry;
use Illuminate\Support\Facades\Schema;

class VisualSearchIndexHealthService
{
    public function __construct(
        private readonly VisualSearchEmbeddingService $embeddingService,
        private readonly ImageFeatureExtractor $featureExtractor,
    ) {}

    public function summary(): array
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
                'entries_matching_current_version' => 0,
                'outdated_embedded_entries' => 0,
                'stale_source_entries' => 0,
                'current_model' => $this->embeddingService->model(),
                'current_embedding_version' => $this->embeddingService->embeddingVersion(),
                'status' => 'index_unavailable',
                'rebuild_guidance' => $this->rebuildGuidance(),
            ];
        }

        $entries = VisualSearchIndexEntry::query()->count();
        $embeddedEntries = VisualSearchIndexEntry::query()->whereNotNull('embedding_vector')->count();
        $currentModel = $this->embeddingService->model();
        $currentVersion = $this->embeddingService->embeddingVersion();
        $matchingCurrentVersion = VisualSearchIndexEntry::query()
            ->whereNotNull('embedding_vector')
            ->where('embedding_model', $currentModel)
            ->where('embedding_version', $currentVersion)
            ->count();
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
            'entries_matching_current_version' => $matchingCurrentVersion,
            'outdated_embedded_entries' => $outdatedEmbeddedEntries,
            'stale_source_entries' => $staleSourceEntries,
            'current_model' => $currentModel,
            'current_embedding_version' => $currentVersion,
            'status' => $this->statusFor($entries, $embeddedEntries, $matchingCurrentVersion),
            'rebuild_guidance' => $this->rebuildGuidance(),
        ];
    }

    public function status(): string
    {
        return $this->summary()['status'] ?? 'index_unavailable';
    }

    private function statusFor(int $entries, int $embeddedEntries, int $matchingCurrentVersion): string
    {
        if ($entries === 0) {
            return 'index_unavailable';
        }

        if ($embeddedEntries > 0 && $matchingCurrentVersion === 0 && $this->embeddingService->enabled()) {
            return 'index_stale';
        }

        return 'ready';
    }

    private function rebuildGuidance(): string
    {
        return 'Run php artisan visual-search:index --fresh to rebuild the visual search index for the active embedding model/version.';
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
