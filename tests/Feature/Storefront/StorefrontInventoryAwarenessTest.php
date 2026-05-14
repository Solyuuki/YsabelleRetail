<?php

use App\Models\Access\Role;
use App\Models\Cart\Cart;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createInventoryAwareCustomer(): User
{
    $customerRole = Role::query()->firstOrCreate(
        ['slug' => 'customer'],
        [
            'name' => 'Customer',
            'description' => 'Customer role',
            'is_system' => true,
        ],
    );

    $user = User::factory()->create();
    $user->roles()->syncWithoutDetaching([$customerRole->id]);

    return $user;
}

function createInventoryAwareProduct(array $productOverrides = [], array $variantOverrides = [], array $inventoryOverrides = []): Product
{
    $category = Category::factory()->create([
        'name' => 'Running',
        'slug' => 'running',
        'is_active' => true,
    ]);

    $product = Product::factory()->for($category)->create(array_merge([
        'name' => 'Atlas Highstreet',
        'slug' => 'atlas-highstreet',
        'description' => 'Inventory-aware storefront fixture.',
        'status' => 'active',
    ], $productOverrides));

    $variant = ProductVariant::factory()->for($product)->create(array_merge([
        'name' => 'Size 10',
        'sku' => 'YS-INV-9300-10',
        'option_values' => [
            'size' => '10',
            'color' => 'Black',
        ],
        'price' => $product->base_price,
        'status' => 'active',
    ], $variantOverrides));

    $variant->inventoryItem()->create(array_merge([
        'quantity_on_hand' => 3,
        'reserved_quantity' => 0,
        'reorder_level' => 2,
        'allow_backorder' => false,
    ], $inventoryOverrides));

    return $product->fresh(['category', 'variants.inventoryItem']);
}

function createInventoryAwareCart(User $user, ProductVariant $variant, int $quantity): Cart
{
    $cart = Cart::query()->create([
        'user_id' => $user->id,
        'status' => 'active',
        'currency' => 'PHP',
        'expires_at' => now()->addDays(7),
    ]);

    $cart->items()->create([
        'product_variant_id' => $variant->id,
        'quantity' => $quantity,
        'unit_price' => $variant->price,
        'line_total' => $quantity * (float) $variant->price,
        'metadata' => [
            'product_slug' => $variant->product->slug,
        ],
    ]);

    return $cart->fresh(['items.variant.product', 'items.variant.inventoryItem']);
}

function storefrontCheckoutPayload(array $overrides = []): array
{
    return array_merge([
        'full_name' => 'Inventory Customer',
        'email' => 'inventory@example.com',
        'phone' => '09171234567',
        'city' => 'Makati',
        'address' => '123 Inventory Street',
        'postal_code' => '1200',
        'order_notes' => null,
        'payment_method' => 'cod',
    ], $overrides);
}

test('add to cart rejects an unavailable variant', function () {
    $product = createInventoryAwareProduct([], [], [
        'quantity_on_hand' => 0,
        'allow_backorder' => false,
    ]);
    $variant = $product->variants->firstOrFail();

    $this->from(route('storefront.catalog.products.show', $product))
        ->post(route('storefront.cart.store'), [
            'variant_id' => $variant->id,
            'quantity' => 1,
        ])
        ->assertRedirect(route('storefront.catalog.products.show', $product))
        ->assertSessionHasErrors([
            'inventory' => 'This variant is currently unavailable.',
        ]);

    expect(Cart::query()->count())->toBe(0);
});

test('add to cart rejects an unavailable exact variant even when another color of the same size is in stock', function () {
    $product = createInventoryAwareProduct([
        'name' => 'Atlas Street',
        'slug' => 'atlas-street',
    ], [
        'option_values' => [
            'size' => '10',
            'color' => 'Stone/Chalk',
        ],
    ], [
        'quantity_on_hand' => 0,
        'allow_backorder' => false,
    ]);

    $soldOutVariant = $product->variants->firstOrFail();
    $product->variants()->create([
        'name' => 'Size 10 Black/White',
        'sku' => 'YS-INV-9300-10-B',
        'option_values' => [
            'size' => '10',
            'color' => 'Black/White',
        ],
        'price' => $product->base_price,
        'status' => 'active',
    ])->inventoryItem()->create([
        'quantity_on_hand' => 4,
        'reserved_quantity' => 0,
        'reorder_level' => 2,
        'allow_backorder' => false,
    ]);

    $this->from(route('storefront.catalog.products.show', $product))
        ->post(route('storefront.cart.store'), [
            'variant_id' => $soldOutVariant->id,
            'quantity' => 1,
        ])
        ->assertRedirect(route('storefront.catalog.products.show', $product))
        ->assertSessionHasErrors([
            'inventory' => 'This variant is currently unavailable.',
        ]);
});

test('add to cart accepts an available variant', function () {
    $product = createInventoryAwareProduct();
    $variant = $product->variants->firstOrFail();

    $this->from(route('storefront.catalog.products.show', $product))
        ->post(route('storefront.cart.store'), [
            'variant_id' => $variant->id,
            'quantity' => 1,
        ])
        ->assertRedirect(route('storefront.catalog.products.show', $product))
        ->assertSessionHasNoErrors();

    expect(Cart::query()->firstOrFail()->items()->count())->toBe(1);
});

test('cart warns when inventory becomes stale before checkout', function () {
    $user = createInventoryAwareCustomer();
    $product = createInventoryAwareProduct();
    $variant = $product->variants->firstOrFail();

    createInventoryAwareCart($user, $variant, 2);
    $variant->inventoryItem()->update([
        'quantity_on_hand' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('storefront.cart.index'))
        ->assertOk()
        ->assertSeeText('Some items in your bag changed while you were shopping.')
        ->assertSeeText('This variant is currently unavailable.');
});

test('checkout remains blocked when stale inventory is no longer available', function () {
    $user = createInventoryAwareCustomer();
    $product = createInventoryAwareProduct();
    $variant = $product->variants->firstOrFail();

    createInventoryAwareCart($user, $variant, 2);
    $variant->inventoryItem()->update([
        'quantity_on_hand' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('storefront.checkout.create'))
        ->assertOk()
        ->assertSeeText('Your cart changed before checkout.')
        ->assertSeeText('Update cart to continue');

    $this->from(route('storefront.checkout.create'))
        ->actingAs($user)
        ->post(route('storefront.checkout.store'), storefrontCheckoutPayload())
        ->assertRedirect(route('storefront.checkout.create'))
        ->assertSessionHasErrors([
            'inventory' => 'The requested quantity is no longer available.',
        ]);
});
