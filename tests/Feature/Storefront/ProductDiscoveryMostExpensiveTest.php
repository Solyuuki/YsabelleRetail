<?php

use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use App\Services\Storefront\ProductDiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeProductDiscoveryCatalogProduct(
    array $productOverrides = [],
    array $variantOverrides = [],
    array $inventoryOverrides = [],
): Product {
    $category = Category::query()->firstOrCreate(
        ['slug' => $productOverrides['category_slug'] ?? 'running'],
        [
            'name' => $productOverrides['category_name'] ?? 'Running',
            'description' => fake()->sentence(),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 25),
        ],
    );

    $product = Product::factory()->for($category)->create(array_merge([
        'name' => 'Discovery Crown',
        'slug' => 'discovery-crown-'.fake()->unique()->numerify('####'),
        'style_code' => 'YS-DISC-'.fake()->unique()->numerify('####'),
        'short_description' => 'Discovery ranking product.',
        'description' => 'Discovery ranking product description.',
        'base_price' => 5990,
        'status' => 'active',
    ], collect($productOverrides)->except(['category_name', 'category_slug'])->all()));

    $variant = ProductVariant::factory()->for($product)->create(array_merge([
        'name' => 'Size 9',
        'sku' => 'YS-DISC-'.fake()->unique()->numerify('####'),
        'option_values' => [
            'size' => '9',
            'color' => 'Black',
        ],
        'price' => $product->base_price,
        'status' => 'active',
    ], $variantOverrides));

    $variant->inventoryItem()->create(array_merge([
        'quantity_on_hand' => 8,
        'reserved_quantity' => 0,
        'reorder_level' => 2,
        'allow_backorder' => false,
    ], $inventoryOverrides));

    return $product->fresh(['category', 'variants.inventoryItem']);
}

test('product discovery ranks most expensive products by highest active selling price', function () {
    makeProductDiscoveryCatalogProduct([
        'name' => 'Sale Mirage Discovery',
        'slug' => 'sale-mirage-discovery',
        'base_price' => 6490,
        'compare_at_price' => 15990,
    ], [
        'sku' => 'YS-SMD-6490-9',
        'price' => 6490,
        'compare_at_price' => 15990,
    ]);

    $ranked = makeProductDiscoveryCatalogProduct([
        'name' => 'Apex Monarch Discovery',
        'slug' => 'apex-monarch-discovery',
        'base_price' => 5990,
        'category_name' => 'Sneakers',
        'category_slug' => 'sneakers',
    ], [
        'sku' => 'YS-AMD-5990-9',
        'price' => 5990,
    ]);

    $topVariant = ProductVariant::factory()->for($ranked)->create([
        'name' => 'Size 10',
        'sku' => 'YS-AMD-8990-10',
        'option_values' => [
            'size' => '10',
            'color' => 'Black',
        ],
        'price' => 8990,
        'compare_at_price' => 10990,
        'status' => 'active',
    ]);

    $topVariant->inventoryItem()->create([
        'quantity_on_hand' => 3,
        'reserved_quantity' => 0,
        'reorder_level' => 1,
        'allow_backorder' => false,
    ]);

    $results = app(ProductDiscoveryService::class)->findMostExpensiveProducts([
        'search' => 'most expensive sneaker',
        'category' => 'sneakers',
    ]);

    expect($results['matches']->first()['product']->slug)->toBe($ranked->slug)
        ->and($results['matches']->first()['highest_price'])->toBe(8990.0)
        ->and($results['matches']->first()['highest_price_label'])->toBe('PHP 8,990');
});

test('product discovery excludes inactive archived and unpriced products from most expensive ranking', function () {
    $active = makeProductDiscoveryCatalogProduct([
        'name' => 'Regal Sprint Discovery',
        'slug' => 'regal-sprint-discovery',
        'base_price' => 6990,
    ], [
        'sku' => 'YS-RSD-6990-9',
        'price' => 6990,
    ]);

    makeProductDiscoveryCatalogProduct([
        'name' => 'Inactive Crown Discovery',
        'slug' => 'inactive-crown-discovery',
        'base_price' => 12990,
        'status' => 'inactive',
    ], [
        'sku' => 'YS-ICD-12990-9',
        'price' => 12990,
    ]);

    makeProductDiscoveryCatalogProduct([
        'name' => 'Archived Crown Discovery',
        'slug' => 'archived-crown-discovery',
        'base_price' => 13990,
        'status' => 'archived',
    ], [
        'sku' => 'YS-ACD-13990-9',
        'price' => 13990,
    ]);

    makeProductDiscoveryCatalogProduct([
        'name' => 'No Price Discovery',
        'slug' => 'no-price-discovery',
        'base_price' => 14990,
    ], [
        'sku' => 'YS-NPD-14990-9',
        'price' => null,
    ]);

    $results = app(ProductDiscoveryService::class)->findMostExpensiveProducts([
        'search' => 'most expensive product',
    ]);

    expect($results['matches']->pluck('product.slug')->all())->toBe([$active->slug]);
});

test('product discovery prefers in stock products before pricier out of stock ones for most expensive ranking', function () {
    makeProductDiscoveryCatalogProduct([
        'name' => 'Dormant Luxe Discovery',
        'slug' => 'dormant-luxe-discovery',
        'base_price' => 9990,
    ], [
        'sku' => 'YS-DLD-9990-9',
        'price' => 9990,
    ], [
        'quantity_on_hand' => 0,
        'reserved_quantity' => 0,
        'reorder_level' => 1,
        'allow_backorder' => false,
    ]);

    $inStock = makeProductDiscoveryCatalogProduct([
        'name' => 'Crown Velocity Discovery',
        'slug' => 'crown-velocity-discovery',
        'base_price' => 7990,
    ], [
        'sku' => 'YS-CVD-7990-9',
        'price' => 7990,
    ], [
        'quantity_on_hand' => 5,
        'reserved_quantity' => 0,
        'reorder_level' => 1,
        'allow_backorder' => false,
    ]);

    $results = app(ProductDiscoveryService::class)->findMostExpensiveProducts([
        'search' => 'highest price product',
    ]);

    expect($results['matches']->first()['product']->slug)->toBe($inStock->slug)
        ->and($results['matches']->first()['availability']['state'])->toBe('in_stock');
});
