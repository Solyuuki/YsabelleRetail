<?php

use App\Models\Access\Role;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductReview;
use App\Models\Catalog\ProductVariant;
use App\Models\Orders\Order;
use App\Models\Orders\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function analyticsReviewCustomer(array $attributes = []): User
{
    $role = Role::query()->firstOrCreate(
        ['slug' => 'customer'],
        [
            'name' => 'Customer',
            'description' => 'Customer role',
            'is_system' => true,
        ],
    );

    $user = User::factory()->create($attributes);
    $user->roles()->syncWithoutDetaching([$role->id]);

    return $user;
}

function analyticsReviewProduct(): Product
{
    $category = Category::factory()->create([
        'name' => 'Review Analytics',
        'slug' => 'review-analytics',
        'is_active' => true,
    ]);

    $product = Product::factory()->for($category)->create([
        'name' => 'Analytics Review Runner',
        'slug' => 'analytics-review-runner',
        'status' => 'active',
    ]);

    $variant = ProductVariant::factory()->for($product)->create([
        'name' => 'Size 8',
        'status' => 'active',
        'price' => 5490,
    ]);

    $variant->inventoryItem()->create([
        'quantity_on_hand' => 10,
        'reserved_quantity' => 0,
        'reorder_level' => 2,
        'allow_backorder' => false,
    ]);

    return $product->fresh(['variants.inventoryItem']);
}

function analyticsExcludedReviewOrderItem(User $user, Product $product): OrderItem
{
    $variant = $product->variants()->firstOrFail();

    $order = Order::query()->create([
        'user_id' => $user->id,
        'source' => 'storefront',
        'order_number' => 'ORD-RVW-REVIEW-001',
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
        'payment_method' => 'card_simulated',
        'exclude_from_analytics' => true,
        'analytics_exclusion_reason' => 'review_support_seed',
        'metadata' => ['demo_seed' => true, 'review_seed' => true],
    ]);

    return $order->items()->create([
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'product_name' => $product->name,
        'variant_name' => $variant->name,
        'sku' => $variant->sku,
        'quantity' => 1,
        'unit_price' => 5490,
        'line_total' => 5490,
        'metadata' => ['demo_seed' => true, 'review_seed' => true],
    ]);
}

test('review eligibility and verified purchase display still work for analytics excluded orders', function () {
    $user = analyticsReviewCustomer(['email' => 'reviewer@example.com']);
    $product = analyticsReviewProduct();
    analyticsExcludedReviewOrderItem($user, $product);

    $this->actingAs($user)
        ->post(route('storefront.catalog.products.reviews.store', $product), [
            'rating' => 5,
            'title' => 'Still verified',
            'body' => 'This review should stay visible even though its supporting order is excluded from analytics.',
        ])
        ->assertRedirect(route('storefront.catalog.products.show', $product).'#reviews');

    $review = ProductReview::query()->firstOrFail();

    expect($review->is_verified_purchase)->toBeTrue()
        ->and($review->order_item_id)->not->toBeNull();

    $this->get(route('storefront.catalog.products.show', $product))
        ->assertOk()
        ->assertSeeText('Still verified')
        ->assertSeeText('Verified purchase');
});
