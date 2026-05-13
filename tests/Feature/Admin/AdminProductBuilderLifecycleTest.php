<?php

use App\Models\Access\Role;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductReview;
use App\Models\Catalog\ProductVariant;
use App\Models\Inventory\StockMovement;
use App\Models\Orders\Order;
use App\Models\Orders\OrderItem;
use App\Models\Storefront\VisualSearchIndexEntry;
use App\Models\User;
use App\Services\Catalog\CatalogQueryService;
use App\Services\Storefront\ProductDiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function createProductBuilderAdmin(array $attributes = []): User
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

function createBuilderProduct(array $productOverrides = [], array $inventoryOverrides = []): Product
{
    $category = Category::factory()->create(['is_active' => true]);
    $product = Product::factory()
        ->for($category)
        ->create(array_replace([
            'name' => 'Builder Runner',
            'slug' => 'builder-runner',
            'primary_image_url' => 'images/products/running/aurum-runner.jpg',
            'status' => 'active',
            'is_featured' => true,
            'force_new_badge' => true,
        ], $productOverrides));

    $variant = ProductVariant::factory()
        ->for($product)
        ->create([
            'name' => 'Size 38 / Black',
            'sku' => 'YSV-BLD-38',
            'option_values' => ['size' => '38', 'color' => 'Black'],
            'price' => 2499,
            'compare_at_price' => 2999,
            'status' => 'active',
        ]);

    $variant->inventoryItem()->create(array_replace([
        'quantity_on_hand' => 8,
        'reserved_quantity' => 0,
        'reorder_level' => 2,
        'allow_backorder' => false,
    ], $inventoryOverrides));

    return $product->fresh(['category', 'variants.inventoryItem']);
}

function builderVariantPayload(ProductVariant $variant, array $overrides = []): array
{
    return array_replace([
        'id' => $variant->id,
        'name' => $variant->name,
        'sku' => $variant->sku,
        'barcode' => $variant->barcode ?? '',
        'size' => $variant->option_values['size'] ?? '',
        'color' => $variant->option_values['color'] ?? '',
        'price' => (string) $variant->price,
        'compare_at_price' => $variant->compare_at_price !== null ? (string) $variant->compare_at_price : '',
        'cost_price' => $variant->cost_price !== null ? (string) $variant->cost_price : '',
        'supplier_name' => $variant->supplier_name ?? '',
        'weight_grams' => $variant->weight_grams !== null ? (string) $variant->weight_grams : '',
        'status' => $variant->status,
        'quantity_on_hand' => (string) ($variant->inventoryItem?->quantity_on_hand ?? 0),
        'reorder_level' => (string) ($variant->inventoryItem?->reorder_level ?? 0),
        'allow_backorder' => ($variant->inventoryItem?->allow_backorder ?? false) ? '1' : '0',
    ], $overrides);
}

function builderUpdatePayload(Product $product, array $variantPayloads, array $productOverrides = []): array
{
    return array_replace_recursive([
        'category_id' => $product->category_id,
        'name' => $product->name,
        'slug' => $product->slug,
        'style_code' => $product->style_code,
        'short_description' => $product->short_description,
        'description' => $product->description,
        'primary_image_url' => $product->primary_image_url,
        'image_alt' => $product->image_alt,
        'status' => $product->status,
        'is_featured' => $product->is_featured ? '1' : '0',
        'force_new_badge' => $product->force_new_badge ? '1' : '0',
        'featured_rank' => $product->featured_rank ?? '',
        'track_inventory' => $product->track_inventory ? '1' : '0',
        'variants' => $variantPayloads,
    ], $productOverrides);
}

function createVisualSearchEntryFor(Product $product): void
{
    $timestamp = now()->subMinute()->startOfSecond();

    VisualSearchIndexEntry::query()->create([
        'product_id' => $product->id,
        'product_variant_id' => $product->variants->first()?->id,
        'image_url' => asset($product->primary_image_url),
        'image_path' => $product->primary_image_url,
        'image_url_hash' => hash('sha256', asset($product->primary_image_url)),
        'image_role' => 'primary',
        'feature_version' => 'v1',
        'source_checksum' => hash('sha256', 'builder-lifecycle'),
        'perceptual_hash' => str_repeat('b', 64),
        'color_histogram' => [0.1, 0.2, 0.3],
        'shape_profile_x' => [0.1, 0.2, 0.3],
        'shape_profile_y' => [0.1, 0.2, 0.3],
        'dominant_colors' => [['hex' => '#111111', 'share' => 1]],
        'mean_red' => 0.1,
        'mean_green' => 0.1,
        'mean_blue' => 0.1,
        'edge_density' => 0.1,
        'foreground_ratio' => 0.1,
        'aspect_ratio' => 1.0,
        'width' => 900,
        'height' => 900,
        'source_updated_at' => $timestamp,
        'indexed_at' => $timestamp,
    ]);
}

test('product builder edit view exposes variant manager hooks and preserves existing variant ids', function () {
    $admin = createProductBuilderAdmin();
    $product = createBuilderProduct();
    $variant = $product->variants->firstOrFail();

    $response = $this->actingAs($admin)
        ->get(route('admin.catalog.products.edit', $product));

    $response
        ->assertOk()
        ->assertSee('data-variant-add', false)
        ->assertSee('data-variant-remove', false)
        ->assertSee('data-variant-duplicate', false)
        ->assertSee('id="variant-template"', false)
        ->assertSee('data-variant-count', false)
        ->assertSee('data-variant-prev', false)
        ->assertSee('data-variant-next', false)
        ->assertSee('value="'.$variant->id.'" data-variant-id', false);
});

test('product builder renders many variants with compact manager controls', function () {
    $admin = createProductBuilderAdmin();
    $product = createBuilderProduct();

    ProductVariant::factory()
        ->count(5)
        ->for($product)
        ->create()
        ->each(function (ProductVariant $variant): void {
            $variant->inventoryItem()->create([
                'quantity_on_hand' => 4,
                'reserved_quantity' => 0,
                'reorder_level' => 1,
                'allow_backorder' => false,
            ]);
        });

    $response = $this->actingAs($admin)
        ->get(route('admin.catalog.products.edit', $product->fresh()));

    expect(substr_count($response->getContent(), 'data-variant-row'))->toBe(7);

    $response
        ->assertSee('data-variant-count', false)
        ->assertSee('data-variant-search', false)
        ->assertSee('Page 1 of', false);
});

test('server rendered live preview reflects active variant price stock and badges', function () {
    $admin = createProductBuilderAdmin();
    $product = createBuilderProduct();

    $response = $this->actingAs($admin)
        ->get(route('admin.catalog.products.edit', $product));

    $response
        ->assertOk()
        ->assertSee('In Stock')
        ->assertDontSee('Set price')
        ->assertSee('&#8369;2,499', false)
        ->assertSee('Featured')
        ->assertSee('Sale')
        ->assertSee('New');
});

test('omitting an existing variant with inventory history archives it instead of deleting it', function () {
    $admin = createProductBuilderAdmin();
    $product = createBuilderProduct();
    $keepVariant = $product->variants->firstOrFail();
    $archiveVariant = ProductVariant::factory()
        ->for($product)
        ->create([
            'name' => 'Size 39 / Black',
            'sku' => 'YSV-BLD-39',
            'option_values' => ['size' => '39', 'color' => 'Black'],
            'price' => 2499,
            'status' => 'active',
        ]);

    $inventoryItem = $archiveVariant->inventoryItem()->create([
        'quantity_on_hand' => 5,
        'reserved_quantity' => 0,
        'reorder_level' => 1,
        'allow_backorder' => false,
    ]);

    StockMovement::query()->create([
        'inventory_item_id' => $inventoryItem->id,
        'product_variant_id' => $archiveVariant->id,
        'type' => 'stock_in',
        'quantity_delta' => 5,
        'reference_number' => 'TEST-ARCHIVE',
        'notes' => 'Seed history',
        'occurred_at' => now(),
    ]);

    $payload = builderUpdatePayload($product, [
        builderVariantPayload($keepVariant),
    ]);

    $this->actingAs($admin)
        ->put(route('admin.catalog.products.update', $product), $payload)
        ->assertRedirect();

    expect($archiveVariant->fresh())
        ->not->toBeNull()
        ->status->toBe('archived');
});

test('omitting an existing variant without history deletes it safely', function () {
    $admin = createProductBuilderAdmin();
    $product = createBuilderProduct();
    $keepVariant = $product->variants->firstOrFail();
    $deleteVariant = ProductVariant::factory()
        ->for($product)
        ->create([
            'name' => 'Size 39 / White',
            'sku' => 'YSV-BLD-39-W',
            'option_values' => ['size' => '39', 'color' => 'White'],
            'price' => 2499,
            'status' => 'active',
        ]);

    $deleteVariant->inventoryItem()->create([
        'quantity_on_hand' => 0,
        'reserved_quantity' => 0,
        'reorder_level' => 0,
        'allow_backorder' => false,
    ]);

    $payload = builderUpdatePayload($product, [
        builderVariantPayload($keepVariant),
    ]);

    $this->actingAs($admin)
        ->put(route('admin.catalog.products.update', $product), $payload)
        ->assertRedirect();

    expect(ProductVariant::query()->find($deleteVariant->id))->toBeNull();
});

test('safe products can be deleted and their visual search entries are cleaned up', function () {
    $admin = createProductBuilderAdmin();
    $product = createBuilderProduct();
    createVisualSearchEntryFor($product);

    $this->actingAs($admin)
        ->delete(route('admin.catalog.products.purge', $product))
        ->assertRedirect(route('admin.catalog.products.index'))
        ->assertSessionHas('toast.title', 'Product deleted');

    expect(Product::query()->find($product->id))->toBeNull()
        ->and(VisualSearchIndexEntry::query()->where('product_id', $product->id)->count())->toBe(0);
});

test('products with order items cannot be deleted', function () {
    $admin = createProductBuilderAdmin();
    $product = createBuilderProduct();
    $variant = $product->variants->firstOrFail();
    $order = Order::query()->create([
        'order_number' => 'ORD-'.Str::upper(Str::random(10)),
        'status' => 'completed',
        'payment_status' => 'paid',
        'fulfillment_status' => 'fulfilled',
        'currency' => 'PHP',
        'subtotal_amount' => 2499,
        'grand_total' => 2499,
        'placed_at' => now(),
    ]);

    OrderItem::query()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'product_name' => $product->name,
        'variant_name' => $variant->name,
        'sku' => $variant->sku,
        'quantity' => 1,
        'unit_price' => 2499,
        'line_total' => 2499,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.catalog.products.purge', $product))
        ->assertRedirect(route('admin.catalog.products.edit', $product))
        ->assertSessionHas('toast.title', 'Delete unavailable');

    expect(Product::query()->find($product->id))->not->toBeNull();
});

test('products with reviews cannot be deleted', function () {
    $admin = createProductBuilderAdmin();
    $product = createBuilderProduct();

    ProductReview::factory()->for($product)->create();

    $this->actingAs($admin)
        ->delete(route('admin.catalog.products.purge', $product))
        ->assertRedirect(route('admin.catalog.products.edit', $product))
        ->assertSessionHas('toast.title', 'Delete unavailable');

    expect(Product::query()->find($product->id))->not->toBeNull();
});

test('products with stock movements cannot be deleted', function () {
    $admin = createProductBuilderAdmin();
    $product = createBuilderProduct();
    $variant = $product->variants->firstOrFail();

    StockMovement::query()->create([
        'inventory_item_id' => $variant->inventoryItem->id,
        'product_variant_id' => $variant->id,
        'type' => 'stock_in',
        'quantity_delta' => 3,
        'reference_number' => 'TEST-STOCK',
        'notes' => 'Seed stock movement',
        'occurred_at' => now(),
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.catalog.products.purge', $product))
        ->assertRedirect(route('admin.catalog.products.edit', $product))
        ->assertSessionHas('toast.title', 'Delete unavailable');

    expect(Product::query()->find($product->id))->not->toBeNull();
});

test('archived products are hidden from storefront catalog queries and chatbot discovery', function () {
    $admin = createProductBuilderAdmin();
    $product = createBuilderProduct();

    $this->actingAs($admin)
        ->delete(route('admin.catalog.products.destroy', $product))
        ->assertRedirect(route('admin.catalog.products.index'));

    $catalogProducts = app(CatalogQueryService::class)
        ->products(['search' => $product->name], 12)
        ->getCollection();
    $directMatch = app(ProductDiscoveryService::class)->findDirectProductMatch($product->name);

    expect($catalogProducts->contains(fn (Product $candidate): bool => $candidate->is($product)))->toBeFalse()
        ->and($directMatch['status'])->not->toBe('active_match')
        ->and($directMatch['status'])->not->toBe('active_close_match');
});

test('visibility diagnostics explain missing variants stock and stale visual search state', function () {
    $admin = createProductBuilderAdmin();
    $product = createBuilderProduct([
        'primary_image_url' => 'images/products/missing/builder-runner.jpg',
    ], [
        'quantity_on_hand' => 0,
        'allow_backorder' => false,
    ]);

    createVisualSearchEntryFor($product);
    $product->update(['primary_image_url' => 'images/products/missing/builder-runner-updated.jpg']);

    $response = $this->actingAs($admin)
        ->get(route('admin.catalog.products.edit', $product->fresh()));

    $response
        ->assertOk()
        ->assertSee('Product Visibility Checklist')
        ->assertSee('The storefront availability rules are currently excluding this product.')
        ->assertSee('Active variants have no stock and backorder is disabled.')
        ->assertSee('The current image path resolves locally, but the file is missing from public storage.')
        ->assertSee('The indexed visual search image is older than the current product image.');
});
