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

Artisan::command('visual-search:index {--fresh : Clear the existing visual search index before rebuilding}', function (): void {
    $stats = app(VisualSearchIndexService::class)->rebuildIndex((bool) $this->option('fresh'));

    $this->table(
        ['Metric', 'Value'],
        [
            ['Products scanned', $stats['products_scanned']],
            ['Images indexed', $stats['images_indexed']],
            ['Images skipped', $stats['images_skipped']],
            ['Embeddings generated', $stats['embeddings_generated']],
            ['Embeddings failed', $stats['embeddings_failed']],
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
    $embedding = app(VisualSearchEmbeddingService::class)->health();
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
            ['Index table exists', $index['table_exists'] ? 'yes' : 'no'],
            ['Index entries', $index['entries']],
            ['Entries with embeddings', $index['embedded_entries']],
            ['Fallback-only entries', $index['fallback_only_entries']],
        ],
    );
})->purpose('Check the local visual search embedding service and index coverage');

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
