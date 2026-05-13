<?php

namespace App\Services\Admin;

use App\Services\Storefront\VisualSearchIndexHealthService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ProductCreationHealthService
{
    private const REQUIRED_COLUMNS = [
        'categories' => [
            'id',
            'slug',
            'is_active',
        ],
        'products' => [
            'category_id',
            'name',
            'slug',
            'style_code',
            'short_description',
            'description',
            'primary_image_url',
            'primary_image_updated_at',
            'image_alt',
            'image_gallery',
            'base_price',
            'compare_at_price',
            'status',
            'is_featured',
            'force_new_badge',
            'featured_rank',
            'track_inventory',
        ],
        'product_variants' => [
            'product_id',
            'name',
            'sku',
            'barcode',
            'option_values',
            'price',
            'compare_at_price',
            'cost_price',
            'supplier_name',
            'weight_grams',
            'status',
        ],
        'inventory_items' => [
            'product_variant_id',
            'quantity_on_hand',
            'reserved_quantity',
            'reorder_level',
            'allow_backorder',
        ],
        'stock_movements' => [
            'inventory_item_id',
            'product_variant_id',
            'actor_id',
            'type',
            'quantity_delta',
            'reference_number',
            'notes',
            'metadata',
            'occurred_at',
        ],
    ];

    public function __construct(
        private readonly VisualSearchIndexHealthService $visualSearchIndexHealth,
    ) {}

    public function snapshot(bool $expectsManagedImageUpload = false): array
    {
        $checks = [
            $this->databaseCheck(),
            $this->schemaCheck(),
            $this->chatbotCatalogCheck(),
            $this->storageCheck($expectsManagedImageUpload),
            $this->visualSearchCheck(),
        ];

        $blockingChecks = array_values(array_filter($checks, fn (array $check): bool => $check['state'] === 'fail'));
        $warningChecks = array_values(array_filter($checks, fn (array $check): bool => $check['state'] === 'warning'));

        return [
            'ready' => $blockingChecks === [],
            'blocking' => $blockingChecks,
            'warnings' => $warningChecks,
            'checks' => $checks,
            'blocking_message' => $blockingChecks[0]['recommendation']
                ?? 'Product creation is temporarily unavailable. Please check catalog system health.',
        ];
    }

    private function databaseCheck(): array
    {
        try {
            DB::connection()->getPdo();
            DB::select('select 1');

            return $this->check(
                key: 'database',
                label: 'Database connection',
                state: 'pass',
                reason: 'The catalog database connection is reachable.',
                recommendation: 'No action needed.',
            );
        } catch (\Throwable $exception) {
            return $this->check(
                key: 'database',
                label: 'Database connection',
                state: 'fail',
                reason: 'The catalog database connection is unavailable.',
                recommendation: 'Product creation is blocked until the database connection is restored.',
            );
        }
    }

    private function schemaCheck(): array
    {
        $missing = [];

        foreach (self::REQUIRED_COLUMNS as $table => $columns) {
            try {
                if (! Schema::hasTable($table)) {
                    $missing[] = "{$table}.*";

                    continue;
                }

                foreach ($columns as $column) {
                    if (! Schema::hasColumn($table, $column)) {
                        $missing[] = "{$table}.{$column}";
                    }
                }
            } catch (\Throwable) {
                $missing[] = "{$table}.*";
            }
        }

        if ($missing === []) {
            return $this->check(
                key: 'schema',
                label: 'Catalog schema',
                state: 'pass',
                reason: 'All required product, variant, inventory, and category columns are present.',
                recommendation: 'No action needed.',
            );
        }

        return $this->check(
            key: 'schema',
            label: 'Catalog schema',
            state: 'fail',
            reason: 'Required catalog columns are missing: '.implode(', ', $missing).'.',
            recommendation: 'Product creation is blocked until the latest catalog migrations are applied.',
        );
    }

    private function chatbotCatalogCheck(): array
    {
        try {
            $tablesReady = Schema::hasTable('products')
                && Schema::hasTable('categories')
                && Schema::hasTable('product_variants');
        } catch (\Throwable) {
            $tablesReady = false;
        }

        if ($tablesReady) {
            return $this->check(
                key: 'chatbot_catalog',
                label: 'Chatbot product discovery',
                state: 'pass',
                reason: 'Product discovery reads live catalog tables, so successful product saves are immediately searchable.',
                recommendation: 'No action needed.',
            );
        }

        return $this->check(
            key: 'chatbot_catalog',
            label: 'Chatbot product discovery',
            state: 'fail',
            reason: 'The live catalog tables required for chatbot discovery are unavailable.',
            recommendation: 'Product creation is blocked until catalog discovery tables are restored.',
        );
    }

    private function storageCheck(bool $expectsManagedImageUpload): array
    {
        $probePath = 'healthchecks/product-create-'.bin2hex(random_bytes(8)).'.txt';

        try {
            Storage::disk('public')->put($probePath, 'ok');
            Storage::disk('public')->delete($probePath);

            return $this->check(
                key: 'storage',
                label: 'Managed image storage',
                state: 'pass',
                reason: 'The public storage disk is writable for uploaded product images.',
                recommendation: 'No action needed.',
            );
        } catch (\Throwable) {
            return $this->check(
                key: 'storage',
                label: 'Managed image storage',
                state: $expectsManagedImageUpload ? 'fail' : 'warning',
                reason: $expectsManagedImageUpload
                    ? 'The public storage disk is not writable for uploaded product images.'
                    : 'The public storage disk is not writable right now, so managed product image uploads may fail.',
                recommendation: $expectsManagedImageUpload
                    ? 'Product creation is blocked until public image storage is writable again.'
                    : 'Restore public storage access before uploading product images.',
            );
        }
    }

    private function visualSearchCheck(): array
    {
        $summary = $this->visualSearchIndexHealth->summary();
        $status = $summary['status'] ?? 'index_unavailable';

        if ($status === 'ready') {
            return $this->check(
                key: 'visual_search',
                label: 'Image search index',
                state: 'pass',
                reason: 'The visual search index is ready for targeted product image sync.',
                recommendation: 'No action needed.',
            );
        }

        return $this->check(
            key: 'visual_search',
            label: 'Image search index',
            state: 'warning',
            reason: 'Visual search is not fully ready right now: '.($summary['rebuild_guidance'] ?? 'Index status requires attention.'),
            recommendation: 'Product creation can continue, but image search sync may be delayed until visual search health is restored.',
        );
    }

    private function check(string $key, string $label, string $state, string $reason, string $recommendation): array
    {
        return compact('key', 'label', 'state', 'reason', 'recommendation');
    }
}
