<?php

use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('catalog api returns seeded products', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create();
    $variant = ProductVariant::factory()->for($product)->create();
    $variant->inventoryItem()->create([
        'quantity_on_hand' => 12,
        'reserved_quantity' => 2,
        'reorder_level' => 3,
        'allow_backorder' => false,
    ]);

    $this->getJson(route('api.v1.catalog.products.index'))
        ->assertOk()
        ->assertJsonPath('data.0.name', $product->name)
        ->assertJsonPath('data.0.variants.0.sku', $variant->sku);
});

test('catalog api returns individual categories by slug', function () {
    $category = Category::factory()->create();

    $this->getJson(route('api.v1.catalog.categories.show', $category))
        ->assertOk()
        ->assertJsonPath('data.slug', $category->slug);
});
