<?php

use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

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

function productVariantButtons(TestResponse $response): array
{
    $document = new DOMDocument();
    @$document->loadHTML($response->getContent());

    $xpath = new DOMXPath($document);
    $nodes = $xpath->query('//button[@data-variant-option]');
    $buttons = [];

    foreach ($nodes as $node) {
        $buttons[] = [
            'id' => $node->attributes->getNamedItem('data-variant-id')?->nodeValue,
            'state' => $node->attributes->getNamedItem('data-variant-state')?->nodeValue,
            'selectable' => $node->attributes->getNamedItem('data-variant-selectable')?->nodeValue,
            'disabled' => $node->attributes->getNamedItem('disabled') !== null,
            'label' => trim($node->textContent),
        ];
    }

    return $buttons;
}

function productColorButtons(TestResponse $response): array
{
    $document = new DOMDocument();
    @$document->loadHTML($response->getContent());

    $xpath = new DOMXPath($document);
    $nodes = $xpath->query('//button[@data-color-option]');
    $buttons = [];

    foreach ($nodes as $node) {
        $buttons[] = [
            'color_key' => $node->attributes->getNamedItem('data-color-key')?->nodeValue,
            'label' => trim($node->textContent),
        ];
    }

    return $buttons;
}

function productAvailabilityPayload(TestResponse $response): array
{
    $document = new DOMDocument();
    @$document->loadHTML($response->getContent());

    $xpath = new DOMXPath($document);
    $node = $xpath->query('//script[@data-product-availability]')->item(0);

    expect($node)->not->toBeNull();

    return json_decode(trim($node->textContent), true, flags: JSON_THROW_ON_ERROR);
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
        ->assertSeeText('Select Color')
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

test('product detail page deduplicates repeated size variants safely', function () {
    $product = createStorefrontProductFixture();

    $product->variants()->create([
        'name' => 'Duplicate Size 9',
        'sku' => 'YS-DUP-9000-9',
        'option_values' => [
            'size' => '9',
            'color' => 'Black/Gold',
        ],
        'price' => $product->base_price,
        'status' => 'active',
    ])->inventoryItem()->create([
        'quantity_on_hand' => 6,
        'reserved_quantity' => 0,
        'reorder_level' => 2,
        'allow_backorder' => false,
    ]);

    $response = $this->get(route('storefront.catalog.products.show', $product))
        ->assertOk();

    $buttons = productVariantButtons($response);
    $colors = productColorButtons($response);

    expect($buttons)->toHaveCount(1)
        ->and($buttons[0]['label'])->toBe('9')
        ->and($colors)->toHaveCount(1)
        ->and($colors[0]['label'])->toBe('Black/Gold');
});

test('product detail page renders size availability for the selected color only', function () {
    $product = createStorefrontProductFixture();

    $product->variants()->create([
        'name' => 'Size 10 Stone/Chalk',
        'sku' => 'YS-OOS-9100-10-A',
        'option_values' => [
            'size' => '10',
            'color' => 'Stone/Chalk',
        ],
        'price' => $product->base_price,
        'status' => 'active',
    ])->inventoryItem()->create([
        'quantity_on_hand' => 0,
        'reserved_quantity' => 0,
        'reorder_level' => 2,
        'allow_backorder' => false,
    ]);

    $product->variants()->create([
        'name' => 'Size 10 Black/White',
        'sku' => 'YS-OOS-9100-10-B',
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

    $response = $this->withSession([
        '_old_input' => [
            'selected_color' => 'stone/chalk',
        ],
    ])->get(route('storefront.catalog.products.show', $product))
        ->assertOk();

    $button = collect(productVariantButtons($response))->firstWhere('label', '10');

    expect($button)->not->toBeNull()
        ->and($button['state'])->toBe('out_of_stock')
        ->and($button['selectable'])->toBe('0')
        ->and($button['disabled'])->toBeTrue();
});

test('switching colors exposes an exact color plus size availability matrix without duplicates', function () {
    $product = createStorefrontProductFixture();

    $product->variants()->create([
        'name' => 'Size 10 Stone/Chalk',
        'sku' => 'YS-MATRIX-9100-10-A',
        'option_values' => [
            'size' => '10',
            'color' => 'Stone/Chalk',
        ],
        'price' => $product->base_price,
        'status' => 'active',
    ])->inventoryItem()->create([
        'quantity_on_hand' => 0,
        'reserved_quantity' => 0,
        'reorder_level' => 2,
        'allow_backorder' => false,
    ]);

    $product->variants()->create([
        'name' => 'Size 10 Black/White',
        'sku' => 'YS-MATRIX-9100-10-B',
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

    $payload = productAvailabilityPayload(
        $this->get(route('storefront.catalog.products.show', $product))->assertOk()
    );

    $colorOptions = collect($payload['color_options'] ?? []);
    $stone = $colorOptions->firstWhere('color_label', 'Stone/Chalk');
    $black = $colorOptions->firstWhere('color_label', 'Black/White');

    expect($colorOptions)->toHaveCount(3)
        ->and($stone['size_options'])->toHaveCount(1)
        ->and($stone['size_options'][0]['size'])->toBe('10')
        ->and($stone['size_options'][0]['state'])->toBe('out_of_stock')
        ->and($black['size_options'])->toHaveCount(1)
        ->and($black['size_options'][0]['size'])->toBe('10')
        ->and($black['size_options'][0]['state'])->toBe('in_stock');
});

test('backorder enabled sizes remain selectable on the product detail page', function () {
    $product = createStorefrontProductFixture();

    $backorderVariant = $product->variants()->create([
        'name' => 'Size 10',
        'sku' => 'YS-BKO-9200-10',
        'option_values' => [
            'size' => '10',
            'color' => 'Black/Gold',
        ],
        'price' => $product->base_price,
        'status' => 'active',
    ]);

    $backorderVariant->inventoryItem()->create([
        'quantity_on_hand' => 0,
        'reserved_quantity' => 0,
        'reorder_level' => 2,
        'allow_backorder' => true,
    ]);

    $response = $this->get(route('storefront.catalog.products.show', $product))
        ->assertOk();

    $button = collect(productVariantButtons($response))->firstWhere('label', '10');

    expect($button)->not->toBeNull()
        ->and($button['state'])->toBe('backorder_available')
        ->and($button['selectable'])->toBe('1')
        ->and($button['disabled'])->toBeFalse();
});

test('product detail page availability copy reflects the selected variant only', function () {
    $product = createStorefrontProductFixture();

    $product->variants()->create([
        'name' => 'Size 10 Black/White',
        'sku' => 'YS-LABEL-9100-10-B',
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

    $this->get(route('storefront.catalog.products.show', $product))
        ->assertOk()
        ->assertSeeText('Select a size to view availability.')
        ->assertDontSeeText('Aurum Runner is in stock right now.');
});
