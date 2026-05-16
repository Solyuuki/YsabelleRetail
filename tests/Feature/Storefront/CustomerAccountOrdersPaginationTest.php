<?php

use App\Models\Access\Role;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use App\Models\Orders\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createAccountOrdersCustomer(): User
{
    $role = Role::query()->firstOrCreate(
        ['slug' => 'customer'],
        [
            'name' => 'Customer',
            'description' => 'Customer role',
            'is_system' => true,
        ],
    );

    $user = User::factory()->create();
    $user->roles()->attach($role);

    return $user;
}

function createAccountOrderForUser(
    User $user,
    int $sequence,
    ?\Illuminate\Support\Carbon $placedAt = null,
    array $overrides = [],
): Order {
    $category = Category::factory()->create([
        'name' => 'Account Order Category '.$sequence,
        'slug' => 'account-order-category-'.$sequence,
        'is_active' => true,
    ]);

    $product = Product::factory()->for($category)->create([
        'name' => $overrides['product_name'] ?? 'Account Order Product '.$sequence,
        'slug' => 'account-order-product-'.$sequence,
        'status' => 'active',
        'primary_image_url' => 'https://cdn.ysabelle.test/account-order-'.$sequence.'.jpg',
        'image_alt' => 'Account order image '.$sequence,
        'image_gallery' => [],
    ]);

    $variant = ProductVariant::factory()->for($product)->create([
        'name' => $overrides['variant_name'] ?? 'Size '.($sequence + 5).' / Black',
        'sku' => 'YS-ACC-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
        'option_values' => [
            'size' => (string) ($sequence + 5),
            'color' => 'Black',
        ],
        'status' => 'active',
    ]);

    $order = Order::query()->create([
        'user_id' => $user->id,
        'source' => 'online',
        'order_number' => $overrides['order_number'] ?? 'YSR-ACC-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
        'status' => $overrides['status'] ?? 'completed',
        'payment_status' => 'paid',
        'fulfillment_status' => 'fulfilled',
        'currency' => 'PHP',
        'subtotal_amount' => $overrides['subtotal_amount'] ?? 5000 + $sequence,
        'discount_amount' => 0,
        'shipping_amount' => 0,
        'tax_amount' => 0,
        'grand_total' => $overrides['grand_total'] ?? 5000 + $sequence,
        'placed_at' => $placedAt ?? now()->subMinutes(10 - $sequence),
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
        'quantity' => $overrides['quantity'] ?? 1,
        'unit_price' => $overrides['unit_price'] ?? 5000 + $sequence,
        'line_total' => $overrides['line_total'] ?? (5000 + $sequence) * ($overrides['quantity'] ?? 1),
        'metadata' => [],
    ]);

    return $order->fresh(['items.product', 'items.variant.product']);
}

test('account page paginates customer orders and shows only the configured page size per page', function () {
    $customer = createAccountOrdersCustomer();

    foreach (range(1, 7) as $sequence) {
        createAccountOrderForUser($customer, $sequence, now()->subDays(8 - $sequence));
    }

    $response = $this->actingAs($customer)
        ->get(route('storefront.account.index'))
        ->assertOk()
        ->assertSee('Your orders')
        ->assertSee('Previous')
        ->assertSee('Next')
        ->assertSee('YSR-ACC-0007')
        ->assertSee('YSR-ACC-0006')
        ->assertSee('YSR-ACC-0005')
        ->assertSee('YSR-ACC-0004')
        ->assertSee('YSR-ACC-0003')
        ->assertDontSee('YSR-ACC-0002')
        ->assertDontSee('YSR-ACC-0001');

    $content = $response->getContent();

    expect(substr_count($content, 'YSR-ACC-000'))->toBe(5);
});

test('account orders are sorted newest first', function () {
    $customer = createAccountOrdersCustomer();

    foreach (range(1, 6) as $sequence) {
        createAccountOrderForUser($customer, $sequence, now()->subHours(7 - $sequence));
    }

    $response = $this->actingAs($customer)
        ->get(route('storefront.account.index'))
        ->assertOk()
        ->assertSeeInOrder([
            'YSR-ACC-0006',
            'YSR-ACC-0005',
            'YSR-ACC-0004',
            'YSR-ACC-0003',
            'YSR-ACC-0002',
        ]);

    $content = $response->getContent();

    expect(strpos($content, 'YSR-ACC-0006'))->toBeLessThan(strpos($content, 'YSR-ACC-0005'))
        ->and(strpos($content, 'YSR-ACC-0005'))->toBeLessThan(strpos($content, 'YSR-ACC-0004'))
        ->and(strpos($content, 'YSR-ACC-0004'))->toBeLessThan(strpos($content, 'YSR-ACC-0003'))
        ->and(strpos($content, 'YSR-ACC-0003'))->toBeLessThan(strpos($content, 'YSR-ACC-0002'));
});

test('account page page 2 shows older orders', function () {
    $customer = createAccountOrdersCustomer();

    foreach (range(1, 7) as $sequence) {
        createAccountOrderForUser($customer, $sequence, now()->subDays(8 - $sequence));
    }

    $this->actingAs($customer)
        ->get(route('storefront.account.index', ['page' => 2]))
        ->assertOk()
        ->assertSee('YSR-ACC-0002')
        ->assertSee('YSR-ACC-0001')
        ->assertDontSee('YSR-ACC-0007')
        ->assertDontSee('YSR-ACC-0006')
        ->assertDontSee('YSR-ACC-0005')
        ->assertDontSee('YSR-ACC-0004')
        ->assertDontSee('YSR-ACC-0003');
});

test('account page shows the empty orders state when the customer has no orders', function () {
    $customer = createAccountOrdersCustomer();

    $this->actingAs($customer)
        ->get(route('storefront.account.index'))
        ->assertOk()
        ->assertSee('No orders yet.')
        ->assertSee('Start shopping')
        ->assertSee('href="'.route('storefront.shop').'"', escape: false);
});

test('account page still displays order items quantity and size information', function () {
    $customer = createAccountOrdersCustomer();

    createAccountOrderForUser($customer, 1, now(), [
        'product_name' => 'Atlas Luxe Runner',
        'variant_name' => 'Size 9 / Ivory',
        'quantity' => 3,
        'order_number' => 'YSR-ACC-ITEMS',
    ]);

    $this->actingAs($customer)
        ->get(route('storefront.account.index'))
        ->assertOk()
        ->assertSee('YSR-ACC-ITEMS')
        ->assertSee('Atlas Luxe Runner')
        ->assertSee('Size 9 / Ivory &middot; Qty 3', escape: false)
        ->assertSee('src="https://cdn.ysabelle.test/account-order-1.jpg"', escape: false);
});
