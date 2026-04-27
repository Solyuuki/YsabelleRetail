<?php

use App\Models\Cart\Cart;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

function makeStorefrontProduct(array $overrides = [], array $variantOverrides = [], array $inventoryOverrides = []): Product
{
    $category = Category::factory()->create([
        'name' => $overrides['category_name'] ?? 'Running',
        'slug' => $overrides['category_slug'] ?? 'running',
    ]);

    $product = Product::factory()->for($category)->create(array_merge([
        'name' => 'Night Runner',
        'slug' => 'night-runner',
        'style_code' => 'YS-'.strtoupper(fake()->unique()->lexify('???')).'-'.fake()->unique()->numerify('####'),
        'short_description' => 'Black performance runner for everyday miles.',
        'description' => 'A black running shoe with stable cushioning and sleek support.',
        'base_price' => 5990,
        'status' => 'active',
    ], collect($overrides)->except(['category_name', 'category_slug'])->all()));

    $variant = ProductVariant::factory()->for($product)->create(array_merge([
        'name' => 'Size 9',
        'sku' => 'YS-NGT-6000-9',
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

test('assistant returns product matches for running shoe questions', function () {
    $product = makeStorefrontProduct();

    $this->postJson(route('storefront.assistant.message'), [
        'message' => 'I need running shoes',
    ])
        ->assertOk()
        ->assertJsonPath('products.0.slug', $product->slug)
        ->assertJsonPath('products.0.availability.state', 'in_stock');
});

test('assistant returns cart guidance from the active cart', function () {
    $user = User::factory()->create();
    $product = makeStorefrontProduct();
    $variant = $product->variants->firstOrFail();

    $cart = Cart::query()->create([
        'user_id' => $user->id,
        'status' => 'active',
        'currency' => 'PHP',
        'expires_at' => now()->addDays(7),
    ]);

    $cart->items()->create([
        'product_variant_id' => $variant->id,
        'quantity' => 2,
        'unit_price' => 5990,
        'line_total' => 11980,
        'metadata' => ['product_slug' => $product->slug],
    ]);

    $this->actingAs($user)
        ->postJson(route('storefront.assistant.message'), [
            'message' => 'What is in my cart?',
        ])
        ->assertOk()
        ->assertJsonPath('products.0.slug', $product->slug)
        ->assertJsonPath('actions.0.label', 'View cart');
});

test('visual search returns similar products from local hints', function () {
    $product = makeStorefrontProduct();

    $this->post(route('storefront.assistant.visual-search'), [
        'image' => UploadedFile::fake()->image('black-runner.png', 600, 600),
        'category' => 'running',
        'color' => 'black',
    ], [
        'Accept' => 'application/json',
    ])
        ->assertOk()
        ->assertJsonPath('products.0.slug', $product->slug);
});

test('visual search rejects invalid file types', function () {
    $this->post(route('storefront.assistant.visual-search'), [
        'image' => UploadedFile::fake()->create('notes.pdf', 64, 'application/pdf'),
    ], [
        'Accept' => 'application/json',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['image']);
});

test('catalog search supports color keywords, price filters, and the cart label', function () {
    $matchingProduct = makeStorefrontProduct([
        'name' => 'Night Runner',
        'slug' => 'night-runner',
        'base_price' => 5990,
    ]);

    makeStorefrontProduct([
        'name' => 'Ivory Prestige',
        'slug' => 'ivory-prestige',
        'base_price' => 6890,
        'category_name' => 'Sneakers',
        'category_slug' => 'sneakers',
    ], [
        'sku' => 'YS-IVR-6890-8',
        'option_values' => [
            'size' => '8',
            'color' => 'Ivory',
        ],
    ]);

    $this->get(route('storefront.shop', [
        'search' => 'black shoes',
        'max_price' => 6000,
    ]))
        ->assertOk()
        ->assertSeeText($matchingProduct->name)
        ->assertDontSeeText('Ivory Prestige')
        ->assertSee('aria-label="Cart"', escape: false)
        ->assertSee('title="Cart"', escape: false)
        ->assertSeeText('Find similar by image');
});
