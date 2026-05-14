<?php

use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use App\Services\Inventory\InventoryManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('storefront.assistant.ai.enabled', false);
});

function makeInventoryAwareAssistantProduct(array $overrides = [], array $variantOverrides = [], array $inventoryOverrides = []): Product
{
    $category = Category::query()->firstOrCreate(
        ['slug' => $overrides['category_slug'] ?? 'running'],
        [
            'name' => $overrides['category_name'] ?? 'Running',
            'description' => fake()->sentence(),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 25),
        ],
    );

    $product = Product::factory()->for($category)->create(array_merge([
        'name' => 'Atlas Highstreet',
        'slug' => 'atlas-highstreet',
        'style_code' => 'YS-INV-'.fake()->unique()->numerify('####'),
        'short_description' => 'Inventory-aware assistant fixture.',
        'description' => 'Inventory-aware assistant fixture.',
        'base_price' => 5990,
        'status' => 'active',
    ], collect($overrides)->except(['category_name', 'category_slug'])->all()));

    $variant = ProductVariant::factory()->for($product)->create(array_merge([
        'name' => 'Size 10',
        'sku' => 'YS-AST-9400-10',
        'option_values' => [
            'size' => '10',
            'color' => 'Black',
        ],
        'price' => $product->base_price,
        'status' => 'active',
    ], $variantOverrides));

    $variant->inventoryItem()->create(array_merge([
        'quantity_on_hand' => 5,
        'reserved_quantity' => 0,
        'reorder_level' => 2,
        'allow_backorder' => false,
    ], $inventoryOverrides));

    return $product->fresh(['category', 'variants.inventoryItem']);
}

function assistantInventoryHeaders(array $headers = []): array
{
    return array_merge([
        'Accept' => 'application/json',
        'X-CSRF-TOKEN' => 'assistant-test-token',
        'X-Requested-With' => 'XMLHttpRequest',
    ], $headers);
}

function assistantInventoryPost($test, string $message)
{
    return $test
        ->withSession(['_token' => 'assistant-test-token'])
        ->postJson(route('storefront.assistant.message'), [
            'message' => $message,
        ], assistantInventoryHeaders());
}

test('assistant low stock query returns real low stock variants', function () {
    makeInventoryAwareAssistantProduct([], [
        'sku' => 'YS-AST-9401-12',
        'option_values' => [
            'size' => '12',
            'color' => 'Black',
        ],
    ], [
        'quantity_on_hand' => 2,
        'reorder_level' => 3,
    ]);

    assistantInventoryPost($this, 'What shoes are low stock?')
        ->assertOk()
        ->assertJsonPath('products.0.slug', 'atlas-highstreet')
        ->assertJsonMissing([
            'answer' => 'No visible products are currently low stock.',
        ])
        ->assertJsonPath('answer', 'Atlas Highstreet size 12 in Black is available in limited stock.');
});

test('assistant out of stock query returns real unavailable variants', function () {
    $product = makeInventoryAwareAssistantProduct([
        'name' => 'Carbon Trace',
        'slug' => 'carbon-trace',
    ], [
        'sku' => 'YS-AST-9402-10',
    ], [
        'quantity_on_hand' => 0,
    ]);

    assistantInventoryPost($this, 'Ano unavailable?')
        ->assertOk()
        ->assertJsonPath('products.0.slug', $product->slug)
        ->assertJsonPath('answer', 'Carbon Trace size 10 in Black is currently unavailable.');
});

test('assistant specific availability checks respect live inventory truth', function () {
    $product = makeInventoryAwareAssistantProduct([
        'name' => 'Atlas Street',
        'slug' => 'atlas-street',
    ], [
        'sku' => 'YS-AST-9403-10',
    ], [
        'quantity_on_hand' => 0,
    ]);

    assistantInventoryPost($this, 'Available ba Atlas Street size 10?')
        ->assertOk()
        ->assertJsonPath('products.0.slug', $product->slug)
        ->assertJsonPath('answer', 'Atlas Street size 10 is currently unavailable. No sizes are currently available.');
});

test('assistant exact availability checks stay color plus size aware', function () {
    $product = makeInventoryAwareAssistantProduct([
        'name' => 'Atlas Street',
        'slug' => 'atlas-street',
    ], [
        'sku' => 'YS-AST-9403-STONE-10',
        'option_values' => [
            'size' => '10',
            'color' => 'Stone/Chalk',
        ],
    ], [
        'quantity_on_hand' => 0,
        'allow_backorder' => false,
    ]);

    $product->variants()->create([
        'name' => 'Size 10 Black/White',
        'sku' => 'YS-AST-9403-BLACK-10',
        'option_values' => [
            'size' => '10',
            'color' => 'Black/White',
        ],
        'price' => $product->base_price,
        'status' => 'active',
    ])->inventoryItem()->create([
        'quantity_on_hand' => 5,
        'reserved_quantity' => 0,
        'reorder_level' => 2,
        'allow_backorder' => false,
    ]);

    assistantInventoryPost($this, 'Is Atlas Street size 10 Stone/Chalk available?')
        ->assertOk()
        ->assertJsonPath('products.0.slug', $product->slug)
        ->assertJsonPath('answer', 'Atlas Street size 10 in Stone/Chalk is currently unavailable. No sizes are currently available.');

    assistantInventoryPost($this, 'Is Atlas Street size 10 Black/White available?')
        ->assertOk()
        ->assertJsonPath('products.0.slug', $product->slug)
        ->assertJsonPath('answer', 'Atlas Street size 10 in Black/White is in stock.');
});

test('assistant recommendations exclude archived and draft products', function () {
    makeInventoryAwareAssistantProduct([
        'name' => 'Archived Trace',
        'slug' => 'archived-trace',
        'status' => 'archived',
    ], [
        'sku' => 'YS-AST-9404-9',
    ]);

    makeInventoryAwareAssistantProduct([
        'name' => 'Draft Trace',
        'slug' => 'draft-trace',
        'status' => 'draft',
    ], [
        'sku' => 'YS-AST-9405-9',
    ]);

    $active = makeInventoryAwareAssistantProduct([
        'name' => 'Live Trace',
        'slug' => 'live-trace',
    ], [
        'sku' => 'YS-AST-9406-9',
    ]);

    assistantInventoryPost($this, 'Find trace shoes')
        ->assertOk()
        ->assertJsonPath('products.0.slug', $active->slug)
        ->assertJsonMissing(['slug' => 'archived-trace'])
        ->assertJsonMissing(['slug' => 'draft-trace']);
});

test('manual stock updates reflect immediately in assistant answers', function () {
    $product = makeInventoryAwareAssistantProduct([
        'name' => 'Signal Runner',
        'slug' => 'signal-runner',
    ], [
        'sku' => 'YS-AST-9407-9',
        'option_values' => [
            'size' => '9',
            'color' => 'Black',
        ],
    ], [
        'quantity_on_hand' => 5,
    ]);

    assistantInventoryPost($this, 'How many stocks does Signal Runner have left?')
        ->assertOk()
        ->assertJsonPath('answer', 'Signal Runner is in stock right now.');

    app(InventoryManager::class)->recordManualChange(
        $product->variants->first(),
        -5,
        'adjustment',
    );

    assistantInventoryPost($this, 'How many stocks does Signal Runner have left?')
        ->assertOk()
        ->assertJsonPath('answer', 'Signal Runner is currently unavailable.');
});

test('assistant does not expose exact quantities by default', function () {
    makeInventoryAwareAssistantProduct([
        'name' => 'Quiet Counter',
        'slug' => 'quiet-counter',
    ], [
        'sku' => 'YS-AST-9408-9',
        'option_values' => [
            'size' => '9',
            'color' => 'Black',
        ],
    ], [
        'quantity_on_hand' => 7,
    ]);

    $response = assistantInventoryPost($this, 'How many stocks does Quiet Counter have left?')
        ->assertOk()
        ->assertJsonPath('answer', 'Quiet Counter is in stock right now.');

    expect($response->json('answer'))->not->toContain('7');
});
