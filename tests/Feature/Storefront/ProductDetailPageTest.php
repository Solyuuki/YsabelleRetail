<?php

use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createStorefrontProductFixture(): Product
{
    $category = Category::factory()->create([
        'name' => 'Running',
        'slug' => 'running',
        'is_active' => true,
    ]);

    $product = Product::factory()->for($category)->create([
        'name' => 'Aurum Runner',
        'slug' => 'aurum-runner',
        'description' => 'Featherlight performance runner crafted for daily movement.',
        'primary_image_url' => 'https://cdn.ysabelle.test/catalog/aurum-runner.jpg',
        'image_alt' => 'Aurum Runner sneaker image',
        'is_featured' => true,
        'featured_rank' => 1,
        'status' => 'active',
    ]);

    $variant = ProductVariant::factory()->for($product)->create([
        'name' => 'Size 9',
        'option_values' => [
            'size' => '9',
            'color' => 'Black/Gold',
        ],
        'status' => 'active',
    ]);

    $variant->inventoryItem()->create([
        'quantity_on_hand' => 12,
        'reserved_quantity' => 2,
        'reorder_level' => 2,
        'allow_backorder' => false,
    ]);

    Product::factory()->for($category)->create([
        'name' => 'Shadow Stride',
        'slug' => 'shadow-stride',
        'primary_image_url' => 'https://cdn.ysabelle.test/catalog/shadow-stride.jpg',
        'status' => 'active',
    ]);

    return $product->fresh(['category', 'variants.inventoryItem']);
}

test('guest users can view the product detail page with trust marks and size selection', function () {
    config()->set('storefront.trust_marks', null);

    $product = createStorefrontProductFixture();

    $this->get(route('storefront.catalog.products.show', $product))
        ->assertOk()
        ->assertSeeText('Aurum Runner')
        ->assertSeeText('Secure Checkout')
        ->assertSeeText('Protected payments and safe transactions.')
        ->assertSee('name="variant_id"', escape: false)
        ->assertSeeText('Select Size (US)')
        ->assertSeeText('Select a size');
});

test('authenticated customers can view the product detail page', function () {
    $product = createStorefrontProductFixture();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('storefront.catalog.products.show', $product))
        ->assertOk()
        ->assertSeeText('Aurum Runner')
        ->assertSeeText('Free Shipping')
        ->assertSee('data-product-form', escape: false)
        ->assertSeeText('Related silhouettes');
});
