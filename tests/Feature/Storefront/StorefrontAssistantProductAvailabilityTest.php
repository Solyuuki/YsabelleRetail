<?php

use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('storefront.assistant.ai.enabled', false);
});

function makeAssistantProductAvailabilityFixture(): Product
{
    $category = Category::query()->firstOrCreate(
        ['slug' => 'running'],
        [
            'name' => 'Running',
            'description' => fake()->sentence(),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 25),
        ],
    );

    $product = Product::factory()->for($category)->create([
        'name' => 'Atlas Street',
        'slug' => 'atlas-street',
        'style_code' => 'YS-CTX-'.fake()->unique()->numerify('####'),
        'short_description' => 'Context-aware stock fixture.',
        'description' => 'Context-aware stock fixture.',
        'base_price' => 5990,
        'status' => 'active',
    ]);

    collect([
        ['size' => '9', 'color' => 'Stone/Chalk', 'sku' => 'YS-CTX-STN-9', 'quantity' => 3],
        ['size' => '10', 'color' => 'Stone/Chalk', 'sku' => 'YS-CTX-STN-10', 'quantity' => 4],
        ['size' => '9', 'color' => 'Black/White', 'sku' => 'YS-CTX-BLK-9', 'quantity' => 5],
        ['size' => '10', 'color' => 'Black/White', 'sku' => 'YS-CTX-BLK-10', 'quantity' => 12],
    ])->each(function (array $variant) use ($product): void {
        $productVariant = ProductVariant::factory()->for($product)->create([
            'name' => 'Size '.$variant['size'].' '.$variant['color'],
            'sku' => $variant['sku'],
            'option_values' => [
                'size' => $variant['size'],
                'color' => $variant['color'],
            ],
            'price' => $product->base_price,
            'status' => 'active',
        ]);

        $productVariant->inventoryItem()->create([
            'quantity_on_hand' => $variant['quantity'],
            'reserved_quantity' => 0,
            'reorder_level' => 2,
            'allow_backorder' => false,
        ]);
    });

    return $product->fresh(['category', 'variants.inventoryItem']);
}

function assistantProductAvailabilityHeaders(array $headers = []): array
{
    return array_merge([
        'Accept' => 'application/json',
        'X-CSRF-TOKEN' => 'assistant-test-token',
        'X-Requested-With' => 'XMLHttpRequest',
    ], $headers);
}

function assistantProductAvailabilityPost($test, string $message, array $overrides = [])
{
    return $test
        ->withSession(['_token' => 'assistant-test-token'])
        ->postJson(route('storefront.assistant.message'), array_merge([
            'message' => $message,
        ], $overrides), assistantProductAvailabilityHeaders());
}

function assistantCurrentProductContext(Product $product, array $overrides = []): array
{
    return [
        'current_product' => array_merge([
            'id' => $product->id,
            'slug' => $product->slug,
            'name' => $product->name,
            'style_code' => $product->style_code,
        ], $overrides),
    ];
}

test('Assistant ProductAvailability current product stock question returns stock answer', function () {
    $product = makeAssistantProductAvailabilityFixture();

    assistantProductAvailabilityPost($this, 'may stock ba nito?', [
        'assistant_context' => assistantCurrentProductContext($product),
    ])
        ->assertOk()
        ->assertJsonPath('answer', 'Atlas Street has 24 pairs across variants. Select a size for exact availability.')
        ->assertJsonPath('products.0.slug', $product->slug);
});

test('Assistant ProductAvailability current product vague stock follow up returns stock answer', function () {
    $product = makeAssistantProductAvailabilityFixture();

    assistantProductAvailabilityPost($this, 'stock?', [
        'assistant_context' => assistantCurrentProductContext($product),
    ])
        ->assertOk()
        ->assertJsonPath('answer', 'Atlas Street has 24 pairs across variants. Select a size for exact availability.')
        ->assertJsonPath('products.0.slug', $product->slug);
});

test('Assistant ProductAvailability selected color without size asks for size', function () {
    $product = makeAssistantProductAvailabilityFixture();

    assistantProductAvailabilityPost($this, 'im talking about stocks', [
        'assistant_context' => assistantCurrentProductContext($product, [
            'selected_color' => 'stone/chalk',
            'selected_color_label' => 'Stone/Chalk',
        ]),
    ])
        ->assertOk()
        ->assertJsonPath('answer', 'Atlas Street Stone/Chalk is selected. Please choose a size so I can check the exact stock.')
        ->assertJsonPath('products.0.slug', $product->slug);
});

test('Assistant ProductAvailability selected color and size returns exact stock', function () {
    $product = makeAssistantProductAvailabilityFixture();

    assistantProductAvailabilityPost($this, 'may size 9 ba?', [
        'assistant_context' => assistantCurrentProductContext($product, [
            'selected_color' => 'stone/chalk',
            'selected_color_label' => 'Stone/Chalk',
            'selected_size' => '9',
        ]),
    ])
        ->assertOk()
        ->assertJsonPath('answer', 'Atlas Street Stone/Chalk size 9 has 3 pairs left.')
        ->assertJsonPath('products.0.slug', $product->slug);
});

test('Assistant ProductAvailability profanity plus stock still routes to stock', function () {
    $product = makeAssistantProductAvailabilityFixture();

    assistantProductAvailabilityPost($this, 'putangina sabi ko stock', [
        'assistant_context' => assistantCurrentProductContext($product, [
            'selected_color' => 'stone/chalk',
            'selected_color_label' => 'Stone/Chalk',
            'selected_size' => '9',
        ]),
    ])
        ->assertOk()
        ->assertJsonPath('answer', 'Atlas Street Stone/Chalk size 9 has 3 pairs left.')
        ->assertJsonPath('products.0.slug', $product->slug);
});

test('Assistant ProductAvailability stock intent does not return product recommendations', function () {
    $product = makeAssistantProductAvailabilityFixture();

    assistantProductAvailabilityPost($this, 'sabi ko stock', [
        'assistant_context' => assistantCurrentProductContext($product, [
            'selected_color' => 'stone/chalk',
            'selected_color_label' => 'Stone/Chalk',
        ]),
    ])
        ->assertOk()
        ->assertJsonPath('products.0.slug', $product->slug)
        ->assertJsonCount(1, 'products')
        ->assertJsonMissing([
            'answer' => 'I did not find an exact match, but these are the closest real products I found.',
        ]);
});

test('Assistant ProductAvailability no current context and vague stock asks clarification', function () {
    makeAssistantProductAvailabilityFixture();

    assistantProductAvailabilityPost($this, 'stock?')
        ->assertOk()
        ->assertJsonPath('answer', 'Tell me which product you want to check, and I will verify the stock for you.')
        ->assertJsonCount(0, 'products');
});

test('Assistant ProductAvailability named product stock question still works', function () {
    $product = makeAssistantProductAvailabilityFixture();

    assistantProductAvailabilityPost($this, 'Atlas Street Stone/Chalk size 9 stock?')
        ->assertOk()
        ->assertJsonPath('products.0.slug', $product->slug)
        ->assertJsonPath('answer', 'Atlas Street size 9 in Stone/Chalk is in stock.');
});
