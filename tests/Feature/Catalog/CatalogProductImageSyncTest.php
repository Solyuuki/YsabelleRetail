<?php

use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Services\Catalog\CatalogProductImageSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

test('catalog image sync replaces generated png references with verified real product photos', function () {
    $category = Category::factory()->create([
        'name' => 'Sync Test',
        'slug' => 'sync-test',
        'is_active' => true,
    ]);

    $directory = public_path('images/products/sync-test');
    File::ensureDirectoryExists($directory);
    File::put("{$directory}/sync-product.jpg", 'real-photo');

    try {
        $product = Product::factory()->for($category)->create([
            'name' => 'Sync Product',
            'slug' => 'sync-product',
            'primary_image_url' => 'images/products/sync-test/sync-product.png',
            'image_gallery' => [
                'images/products/sync-test/sync-product-gallery-1.png',
                'images/products/sync-test/sync-product-gallery-2.png',
                'images/products/sync-test/sync-product-gallery-3.png',
            ],
            'status' => 'active',
        ]);

        $stats = app(CatalogProductImageSyncService::class)->sync();

        expect($stats['products_updated'])->toBe(1)
            ->and($stats['missing_sources'])->toBe([]);

        $product->refresh();

        expect($product->primary_image_url)->toBe('images/products/sync-test/sync-product.jpg')
            ->and($product->image_gallery)->toBe([]);
    } finally {
        File::deleteDirectory($directory);
    }
});
