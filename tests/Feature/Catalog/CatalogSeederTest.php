<?php

use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use Database\Seeders\Catalog\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('catalog seeder creates a large deterministic shoe catalog with mapped inventory', function () {
    $this->seed(CatalogSeeder::class);

    expect(Category::query()->pluck('name')->all())->toContain(
        'Running Shoes',
        'Sneakers',
        'Basketball Shoes',
        'Lifestyle Shoes',
        'Training Shoes',
        'Walking Shoes',
        'Slip-ons',
        'Boots / High-cut Shoes',
    );

    expect(Product::query()->count())->toBeGreaterThanOrEqual(100)
        ->and(Product::query()->distinct('name')->count('name'))->toBe(Product::query()->count())
        ->and(Product::query()->whereNull('style_code')->count())->toBe(0)
        ->and(Product::query()->distinct('style_code')->count('style_code'))->toBe(Product::query()->count());

    expect(ProductVariant::query()->count())->toBeGreaterThan(500)
        ->and(ProductVariant::query()->distinct('sku')->count('sku'))->toBe(ProductVariant::query()->count())
        ->and(ProductVariant::query()->doesntHave('inventoryItem')->count())->toBe(0);

    $variant = ProductVariant::query()
        ->where('sku', 'YS-AUR-7490-9')
        ->with(['product.category', 'inventoryItem'])
        ->firstOrFail();

    expect($variant->product->name)->toBe('Aurum Runner')
        ->and($variant->product->category?->name)->toBe('Running Shoes')
        ->and($variant->inventoryItem?->quantity_on_hand)->toBeGreaterThan(0);
});
