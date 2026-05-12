<?php

use App\Models\Access\Role;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use App\Models\Orders\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function orderImageRoleUser(string $slug, string $name): User
{
    $role = Role::query()->firstOrCreate(
        ['slug' => $slug],
        [
            'name' => $name,
            'description' => $name.' role',
            'is_system' => true,
        ],
    );

    $user = User::factory()->create();
    $user->roles()->attach($role);

    return $user;
}

function orderImageProductFixture(array $productOverrides = [], array $variantOverrides = []): Product
{
    $category = Category::factory()->create([
        'name' => 'Order Images '.random_int(100, 999),
        'slug' => 'order-images-'.random_int(1000, 9999),
        'is_active' => true,
    ]);

    $product = Product::factory()->for($category)->create(array_merge([
        'name' => 'Atlas Highstreet',
        'slug' => 'atlas-highstreet-'.random_int(1000, 9999),
        'status' => 'active',
        'primary_image_url' => 'https://cdn.ysabelle.test/catalog/atlas-highstreet.jpg',
        'image_alt' => 'Atlas Highstreet product image',
        'image_gallery' => [],
    ], $productOverrides));

    $variant = ProductVariant::factory()->for($product)->create(array_merge([
        'name' => 'Size 9 / Black',
        'sku' => 'YS-ATL-6200-9',
        'option_values' => ['size' => '9', 'color' => 'Black'],
        'status' => 'active',
    ], $variantOverrides));

    $variant->inventoryItem()->create([
        'quantity_on_hand' => 8,
        'reserved_quantity' => 0,
        'reorder_level' => 2,
        'allow_backorder' => false,
    ]);

    return $product->fresh(['category', 'variants.inventoryItem']);
}

function orderImageOrderFixture(User $user, Product $product, array $itemMetadata = []): Order
{
    $variant = $product->variants()->firstOrFail();

    $order = Order::query()->create([
        'user_id' => $user->id,
        'source' => 'online',
        'order_number' => 'YSR-IMG-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
        'status' => 'completed',
        'payment_status' => 'paid',
        'fulfillment_status' => 'fulfilled',
        'currency' => 'PHP',
        'subtotal_amount' => 5490,
        'discount_amount' => 0,
        'shipping_amount' => 0,
        'tax_amount' => 0,
        'grand_total' => 5490,
        'placed_at' => now(),
        'customer_name' => $user->name,
        'customer_email' => $user->email,
        'metadata' => ['source' => 'online'],
    ]);

    $order->items()->create([
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'product_name' => $product->name,
        'variant_name' => $variant->name,
        'sku' => $variant->sku,
        'quantity' => 1,
        'unit_price' => 5490,
        'line_total' => 5490,
        'metadata' => $itemMetadata,
    ]);

    return $order->fresh(['items.product', 'items.variant.product']);
}

test('account order items fall back to the current product image when the snapshot is missing', function () {
    $customer = orderImageRoleUser('customer', 'Customer');
    $product = orderImageProductFixture();
    orderImageOrderFixture($customer, $product, [
        'product_image_alt' => 'Atlas Highstreet snapshot image',
    ]);

    $this->actingAs($customer)
        ->get(route('storefront.account.index'))
        ->assertOk()
        ->assertSee('src="https://cdn.ysabelle.test/catalog/atlas-highstreet.jpg"', escape: false)
        ->assertDontSee('src="YOURS CATALOG IMAGE"', escape: false);
});

test('admin order detail hides invalid snapshot image text and shows the branded placeholder instead', function () {
    $admin = orderImageRoleUser('admin', 'Admin');
    $customer = orderImageRoleUser('customer', 'Customer');
    $product = orderImageProductFixture([
        'name' => 'Placeholder Pair',
        'primary_image_url' => null,
        'image_alt' => null,
        'image_gallery' => [],
    ], [
        'sku' => 'YS-PLC-6200-9',
    ]);

    $order = orderImageOrderFixture($customer, $product, [
        'product_image_url' => 'YOURS CATALOG IMAGE',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertSeeText('Curated catalog imagery')
        ->assertDontSee('src="YOURS CATALOG IMAGE"', escape: false);
});
