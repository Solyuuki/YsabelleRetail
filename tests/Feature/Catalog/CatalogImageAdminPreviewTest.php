<?php

use App\Models\Access\Role;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createCatalogImageAdmin(array $attributes = []): User
{
    $role = Role::query()->firstOrCreate(
        ['slug' => 'admin'],
        [
            'name' => 'Admin',
            'description' => 'Admin role',
            'is_system' => true,
        ],
    );

    $user = User::factory()->create($attributes);
    $user->roles()->attach($role);

    return $user;
}

function createCatalogImageProduct(array $productOverrides = [], array $variantOverrides = []): Product
{
    $category = Category::factory()->create(['is_active' => true]);
    $product = Product::factory()
        ->for($category)
        ->create(array_replace([
            'name' => 'Ivory Street',
            'slug' => 'ivory-street',
            'primary_image_url' => 'images/products/running/aurum-runner.jpg',
            'status' => 'active',
        ], $productOverrides));

    $variant = ProductVariant::factory()
        ->for($product)
        ->create(array_replace([
            'name' => 'Size 38 / Ivory',
            'sku' => 'YSV-IVORY-38',
            'option_values' => ['size' => '38', 'color' => 'Ivory'],
            'price' => 2599,
            'compare_at_price' => 2999,
            'status' => 'active',
        ], $variantOverrides));

    $variant->inventoryItem()->create([
        'quantity_on_hand' => 10,
        'reserved_quantity' => 0,
        'reorder_level' => 2,
        'allow_backorder' => false,
    ]);

    return $product->fresh(['category', 'variants.inventoryItem']);
}

test('existing storage image preview data is present in the admin product builder', function () {
    $admin = createCatalogImageAdmin();
    $product = createCatalogImageProduct([
        'primary_image_url' => 'storage/products/25/primary-preview.jpg',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.catalog.products.edit', $product));

    $response
        ->assertOk()
        ->assertSee('data-initial-image-mode="path"', false)
        ->assertSee(e(asset('storage/products/25/primary-preview.jpg')), false);
});

test('existing seeded image preview data is present in the admin product builder', function () {
    $admin = createCatalogImageAdmin();
    $product = createCatalogImageProduct([
        'primary_image_url' => 'images/products/running/aurum-runner.jpg',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.catalog.products.edit', $product));

    $response
        ->assertOk()
        ->assertSee('data-initial-image-mode="path"', false)
        ->assertSee(e(asset('images/products/running/aurum-runner.jpg')), false);
});

test('gallery fallback image preview data remains available when a product has no primary image path', function () {
    $admin = createCatalogImageAdmin();
    $product = createCatalogImageProduct([
        'primary_image_url' => null,
        'image_gallery' => ['https://cdn.example.test/products/ivory-street-gallery.jpg'],
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.catalog.products.edit', $product));

    $response
        ->assertOk()
        ->assertSee('data-initial-image-mode="fallback"', false)
        ->assertSee('https://cdn.example.test/products/ivory-street-gallery.jpg', false);
});

test('external image urls are passed through safely to the admin product builder preview', function () {
    $admin = createCatalogImageAdmin();
    $product = createCatalogImageProduct([
        'primary_image_url' => 'https://cdn.example.test/products/ivory-street.jpg',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.catalog.products.edit', $product));

    $response
        ->assertOk()
        ->assertSee('data-initial-image-mode="path"', false)
        ->assertSee('https://cdn.example.test/products/ivory-street.jpg', false);
});

test('broken local image paths still render branded fallback and diagnostics guidance', function () {
    $admin = createCatalogImageAdmin();
    $product = createCatalogImageProduct([
        'primary_image_url' => 'images/products/missing/ivory-street.jpg',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.catalog.products.edit', $product));

    $response
        ->assertOk()
        ->assertSee('data-live-preview-fallback', false)
        ->assertSee('data-product-image-placeholder', false)
        ->assertSee('Product Visibility Checklist')
        ->assertSee('The current image path resolves locally, but the file is missing from public storage.');
});
