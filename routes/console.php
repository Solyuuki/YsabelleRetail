<?php

declare(strict_types=1);

use App\Services\Catalog\CatalogImageAuditService;
use App\Services\Catalog\CatalogProductImageSyncService;
use App\Services\Storefront\Assistant\StorefrontAssistantGuidanceService;
use App\Services\Storefront\VisualSearchEmbeddingService;
use App\Services\Storefront\VisualSearchIndexService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('visual-search:index {--fresh : Clear the existing visual search index before rebuilding} {--limit= : Limit products/images for debugging}', function (): void {
    $fresh = (bool) $this->option('fresh');
    $limitOption = $this->option('limit');
    $limit = is_numeric($limitOption) && (int) $limitOption > 0 ? (int) $limitOption : null;

    $this->components->info(sprintf(
        'Starting visual search indexing%s%s',
        $fresh ? ' with fresh replacement' : '',
        $limit !== null ? " (limit: {$limit})" : '',
    ));

    $stats = app(VisualSearchIndexService::class)->rebuildIndex($fresh, $limit);

    $this->table(
        ['Metric', 'Value'],
        [
            ['Product limit', $limit ?? 'none'],
            ['Products scanned', $stats['products_scanned']],
            ['Products with images', $stats['products_with_images']],
            ['Products skipped', $stats['products_skipped']],
            ['Images discovered', $stats['images_discovered']],
            ['Images indexed', $stats['images_indexed']],
            ['Images skipped', $stats['images_skipped']],
            ['Missing image files', $stats['missing_image_files']],
            ['Embeddings generated', $stats['embeddings_generated']],
            ['Embeddings failed', $stats['embeddings_failed']],
            ['Index entries before', $stats['index_entries_before']],
            ['Entries with embeddings before', $stats['entries_with_embeddings_before']],
            ['Index entries after', $stats['index_entries_after']],
            ['Entries with embeddings after', $stats['entries_with_embeddings_after']],
            ['Entries deleted', $stats['entries_deleted']],
        ],
    );
})->purpose('Build the visual product search index from catalog images');

Artisan::command('visual-search:clear', function (): void {
    $deleted = app(VisualSearchIndexService::class)->clearIndex();

    $this->table(
        ['Metric', 'Value'],
        [
            ['Entries deleted', $deleted],
        ],
    );
})->purpose('Clear the visual product search index');

Artisan::command('visual-search:health', function (): void {
    $embeddingService = app(VisualSearchEmbeddingService::class);
    $embedding = $embeddingService->health();
    $runtime = $embeddingService->runtimeDetails();
    $index = app(VisualSearchIndexService::class)->health();

    $this->table(
        ['Metric', 'Value'],
        [
            ['Embedding configured', $embedding['configured'] ? 'yes' : 'no'],
            ['Embedding reachable', $embedding['reachable'] ? 'yes' : 'no'],
            ['Embedding available', $embedding['available'] ? 'yes' : 'no'],
            ['Embedding model', $embedding['model'] ?? 'n/a'],
            ['Embedding version', $embedding['embedding_version'] ?? 'n/a'],
            ['Embedding device', $embedding['device'] ?? 'n/a'],
            ['Embedding message', $embedding['message'] ?? 'n/a'],
            ['Configured Python', $runtime['configured_python_binary'] ?? 'n/a'],
            ['Configured Python exists', ($runtime['configured_python_binary_exists'] ?? false) ? 'yes' : 'no'],
            ['Env-file Python', $runtime['env_file_python_binary'] ?? 'n/a'],
            ['Env-file Python exists', ($runtime['env_file_python_binary_exists'] ?? false) ? 'yes' : 'no'],
            ['Resolved Python', $runtime['resolved_python_binary'] ?? 'n/a'],
            ['Embedding script', $runtime['script_path'] ?? 'n/a'],
            ['Embedding script exists', ($runtime['script_exists'] ?? false) ? 'yes' : 'no'],
            ['GD available', ($index['gd_available'] ?? false) ? 'yes' : 'no'],
            ['GD message', $index['gd_message'] ?? 'n/a'],
            ['Index table exists', $index['table_exists'] ? 'yes' : 'no'],
            ['Index entries', $index['entries']],
            ['Entries with embeddings', $index['embedded_entries']],
            ['Entries matching current version', $index['entries_matching_current_version'] ?? 0],
            ['Fallback-only entries', $index['fallback_only_entries']],
            ['Indexed model', $index['current_model'] ?? 'n/a'],
            ['Indexed version', $index['current_embedding_version'] ?? 'n/a'],
            ['Outdated embedded entries', $index['outdated_embedded_entries'] ?? 0],
            ['Source-stale entries', $index['stale_source_entries'] ?? 0],
            ['Index status', $index['status'] ?? 'n/a'],
            ['Rebuild guidance', $index['rebuild_guidance'] ?? 'n/a'],
        ],
    );
})->purpose('Check the local visual search embedding service and index coverage');

Artisan::command('visual-search:doctor', function (): int {
    $embeddingService = app(VisualSearchEmbeddingService::class);
    $doctor = $embeddingService->runtimeDetails(true);
    $health = $embeddingService->health();
    $index = app(VisualSearchIndexService::class)->health();
    $dependencies = $doctor['dependencies'] ?? [];

    $this->table(
        ['Check', 'Result'],
        [
            ['Embedding enabled', $embeddingService->enabled() ? 'yes' : 'no'],
            ['Configured Python', $doctor['configured_python_binary'] ?? 'n/a'],
            ['Configured Python exists', ($doctor['configured_python_binary_exists'] ?? false) ? 'yes' : 'no'],
            ['Env-file Python', $doctor['env_file_python_binary'] ?? 'n/a'],
            ['Env-file Python exists', ($doctor['env_file_python_binary_exists'] ?? false) ? 'yes' : 'no'],
            ['Resolved Python', $doctor['resolved_python_binary'] ?? 'n/a'],
            ['Python version', $doctor['python_version'] ?? 'n/a'],
            ['Python version reachable', ($doctor['python_version_ok'] ?? false) ? 'yes' : 'no'],
            ['numpy import', ($dependencies['numpy']['ok'] ?? false) ? 'ok' : ($dependencies['numpy']['message'] ?? 'failed')],
            ['torch import', ($dependencies['torch']['ok'] ?? false) ? 'ok' : ($dependencies['torch']['message'] ?? 'failed')],
            ['transformers import', ($dependencies['transformers']['ok'] ?? false) ? 'ok' : ($dependencies['transformers']['message'] ?? 'failed')],
            ['Embedding model', $doctor['model'] ?? 'n/a'],
            ['Embedding version', $doctor['embedding_version'] ?? 'n/a'],
            ['Embedding reachable', ($health['reachable'] ?? false) ? 'yes' : 'no'],
            ['Embedding available', ($health['available'] ?? false) ? 'yes' : 'no'],
            ['Embedding message', $health['message'] ?? 'n/a'],
            ['Script path', $doctor['script_path'] ?? 'n/a'],
            ['Script exists', ($doctor['script_exists'] ?? false) ? 'yes' : 'no'],
            ['Index entries', $index['entries'] ?? 0],
            ['Entries with embeddings', $index['embedded_entries'] ?? 0],
            ['Entries matching current version', $index['entries_matching_current_version'] ?? 0],
            ['Outdated embedded entries', $index['outdated_embedded_entries'] ?? 0],
            ['Source-stale entries', $index['stale_source_entries'] ?? 0],
            ['Index status', $index['status'] ?? 'n/a'],
            ['Recommended fix', $doctor['recommended_fix'] ?? ($index['rebuild_guidance'] ?? 'n/a')],
        ],
    );

    return (($health['available'] ?? false) && ($index['entries'] ?? 0) > 0) ? 0 : 1;
})->purpose('Diagnose visual search runtime, Python dependencies, and index readiness');

Artisan::command('catalog:images:audit', function (): int {
    $audit = app(CatalogImageAuditService::class)->audit();

    $status = strtoupper($audit['status']);
    $statusMethod = match ($audit['status']) {
        'green' => 'info',
        'warning' => 'warn',
        default => 'error',
    };

    $this->components->{$statusMethod}("Catalog image audit status: {$status}");

    $this->table(
        ['Metric', 'Value'],
        [
            ['Total products', $audit['products']['total']],
            ['Products with primary images', $audit['products']['with_primary']],
            ['Products missing primary images', $audit['products']['missing_primary']],
            ['Total primary image URLs', $audit['primary_images']['total_urls']],
            ['Distinct primary image URLs', $audit['primary_images']['distinct_urls']],
            ['Distinct normalized primary URLs', $audit['primary_images']['distinct_normalized_urls']],
            ['Primary image uniqueness ratio', number_format($audit['primary_images']['uniqueness_ratio'] * 100, 2).'%'],
            ['Products with gallery images', $audit['products']['with_gallery']],
            ['Average gallery images per product', number_format($audit['products']['average_gallery_images'], 2)],
            ['Distinct gallery image URLs', $audit['gallery_images']['distinct_urls']],
            ['Visual-search index entries', $audit['index']['entries']],
            ['Distinct indexed image URLs', $audit['index']['distinct_indexed_image_urls']],
            ['Entries with embeddings', $audit['index']['entries_with_embeddings']],
            ['Entries matching current version', $audit['index']['entries_matching_current_version'] ?? 0],
            ['Outdated embedded entries', $audit['index']['outdated_embedded_entries'] ?? 0],
            ['Fallback-only index entries', $audit['index']['fallback_only_entries']],
        ],
    );

    $duplicateSections = [
        'Duplicate primary image URLs' => $audit['duplicates']['exact_url'],
        'Duplicate normalized primary image URLs' => $audit['duplicates']['normalized_url'],
        'Duplicate local file paths' => $audit['duplicates']['local_path'],
        'Duplicate URL hashes' => $audit['duplicates']['url_hash'],
        'Duplicate perceptual hashes' => $audit['duplicates']['perceptual_hash'],
    ];

    foreach ($duplicateSections as $label => $groups) {
        if ($groups === []) {
            $this->line($label.': none');

            continue;
        }

        $this->line($label.':');

        foreach ($groups as $group) {
            $this->line('  - '.$group['value']);

            foreach ($group['products'] as $product) {
                $this->line(sprintf('      * %s [%s]', $product['name'], $product['category'] ?? 'n/a'));
            }
        }
    }

    foreach ($audit['warnings'] as $warning) {
        $this->components->warn($warning);
    }

    foreach ($audit['errors'] as $error) {
        $this->components->error($error);
    }

    return $audit['status'] === 'red' ? 1 : 0;
})->purpose('Audit catalog image quality, uniqueness, and visual-search index coverage');

Artisan::command('catalog:images:sync {--dry-run : Preview the normalization without updating the database}', function (): int {
    $persist = ! (bool) $this->option('dry-run');
    $stats = app(CatalogProductImageSyncService::class)->sync($persist);

    $this->table(
        ['Metric', 'Value'],
        [
            ['Products scanned', $stats['products_scanned']],
            ['Products updated', $persist ? $stats['products_updated'] : 'dry-run'],
            ['Images copied', $stats['images_copied']],
            ['Images already present', $stats['images_already_present']],
            ['Missing sources', count($stats['missing_sources'])],
        ],
    );

    if ($stats['missing_sources'] !== []) {
        $this->components->error('Some product images could not be synced.');

        foreach ($stats['missing_sources'] as $row) {
            $this->line(sprintf(
                '  - %s [%s] source: %s',
                $row['product'],
                $row['variant'],
                $row['source'] ?? 'n/a',
            ));
        }

        return 1;
    }

    $this->components->info($persist
        ? 'Catalog product images are normalized to public/images/products and the database now stores local asset paths.'
        : 'Dry run completed. Re-run without --dry-run to persist the normalized asset paths.');

    return 0;
})->purpose('Copy catalog product images into public/images/products and normalize product image paths');

Artisan::command('assistant:ollama-health', function (): void {
    $health = app(StorefrontAssistantGuidanceService::class)->health();

    $this->table(
        ['Metric', 'Value'],
        [
            ['Configured', $health['configured'] ? 'yes' : 'no'],
            ['Reachable', $health['reachable'] ? 'yes' : 'no'],
            ['Model', $health['model'] ?: 'n/a'],
            ['Model available', $health['model_available'] ? 'yes' : 'no'],
            ['Message', $health['message']],
        ],
    );
})->purpose('Check the local Ollama shopping-assistant provider');
