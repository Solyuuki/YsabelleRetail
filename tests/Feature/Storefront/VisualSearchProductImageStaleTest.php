<?php

use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Storefront\VisualSearchIndexEntry;
use App\Services\Storefront\VisualSearchIndexService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createVisualSearchIndexedProduct(array $overrides = []): Product
{
    $category = Category::factory()->create([
        'name' => 'Visual Search Running',
        'slug' => 'visual-search-running',
        'is_active' => true,
    ]);

    return Product::factory()->for($category)->create(array_replace([
        'name' => 'Visual Search Runner',
        'slug' => 'visual-search-runner',
        'primary_image_url' => 'images/products/running/aurum-runner.jpg',
        'status' => 'active',
    ], $overrides));
}

function createVisualSearchIndexEntry(Product $product): void
{
    $timestamp = now()->subMinute()->startOfSecond();

    VisualSearchIndexEntry::query()->create([
        'product_id' => $product->id,
        'product_variant_id' => null,
        'image_url' => asset($product->primary_image_url),
        'image_path' => $product->primary_image_url,
        'image_url_hash' => hash('sha256', asset($product->primary_image_url)),
        'image_role' => 'primary',
        'feature_version' => 'v1',
        'source_checksum' => hash('sha256', 'visual-search-product-image-stale'),
        'perceptual_hash' => str_repeat('a', 64),
        'color_histogram' => [0.1, 0.2, 0.3],
        'shape_profile_x' => [0.1, 0.2, 0.3],
        'shape_profile_y' => [0.1, 0.2, 0.3],
        'dominant_colors' => [['hex' => '#111111', 'share' => 1]],
        'mean_red' => 0.1,
        'mean_green' => 0.1,
        'mean_blue' => 0.1,
        'edge_density' => 0.1,
        'foreground_ratio' => 0.1,
        'aspect_ratio' => 1.0,
        'width' => 900,
        'height' => 900,
        'source_updated_at' => $timestamp,
        'indexed_at' => $timestamp,
    ]);
}

test('visual search marks indexed entries as stale when a product primary image changes', function () {
    $product = createVisualSearchIndexedProduct();
    createVisualSearchIndexEntry($product);

    expect(app(VisualSearchIndexService::class)->health()['stale_source_entries'])->toBe(0);

    $product->update([
        'primary_image_url' => 'images/products/running/shadow-stride.jpg',
    ]);

    expect(app(VisualSearchIndexService::class)->health()['stale_source_entries'])->toBe(1)
        ->and(VisualSearchIndexEntry::query()->value('source_updated_at'))->not->toBeNull();
});

test('visual search does not mark indexed entries as stale for non image product edits', function () {
    $product = createVisualSearchIndexedProduct();
    createVisualSearchIndexEntry($product);

    $product->update([
        'name' => 'Visual Search Runner II',
    ]);

    expect(app(VisualSearchIndexService::class)->health()['stale_source_entries'])->toBe(0);
});
