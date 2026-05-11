<?php

use App\Models\Access\Role;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductReview;
use App\Models\Catalog\ProductVariant;
use App\Models\Orders\Order;
use App\Models\Orders\OrderItem;
use App\Models\User;
use App\Services\Catalog\ProductReviewAggregateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function reviewCustomer(): User
{
    $role = Role::query()->firstOrCreate(
        ['slug' => 'customer'],
        ['name' => 'Customer', 'description' => 'Customer role', 'is_system' => true],
    );

    $user = User::factory()->create();
    $user->roles()->attach($role);

    return $user;
}

function reviewProductFixture(array $overrides = []): Product
{
    $category = Category::factory()->create([
        'name' => 'Running '.random_int(100, 999),
        'slug' => 'running-'.random_int(1000, 9999),
        'is_active' => true,
    ]);

    $product = Product::factory()->for($category)->create(array_merge([
        'name' => 'Trust Runner',
        'slug' => 'trust-runner',
        'status' => 'active',
        'base_price' => 5490,
        'compare_at_price' => 6490,
        'created_at' => Carbon::parse('2026-05-05 12:00:00'),
        'updated_at' => Carbon::parse('2026-05-05 12:00:00'),
    ], $overrides));

    $variant = ProductVariant::factory()->for($product)->create([
        'name' => 'Size 9',
        'option_values' => ['size' => '9', 'color' => 'Black/Gold'],
        'status' => 'active',
    ]);

    $variant->inventoryItem()->create([
        'quantity_on_hand' => 12,
        'reserved_quantity' => 0,
        'reorder_level' => 2,
        'allow_backorder' => false,
    ]);

    return $product->fresh(['category', 'variants.inventoryItem']);
}

function completedPaidOrderItem(User $user, Product $product): OrderItem
{
    $variant = $product->variants()->firstOrFail();

    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'ORD-REV-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
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
    ]);

    return $order->items()->create([
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'product_name' => $product->name,
        'variant_name' => $variant->name,
        'sku' => $variant->sku,
        'quantity' => 1,
        'unit_price' => $product->base_price,
        'line_total' => $product->base_price,
    ]);
}

function reviewPayload(array $overrides = []): array
{
    return array_merge([
        'rating' => 5,
        'title' => 'Exactly what I wanted',
        'body' => 'Comfortable out of the box, stable on pavement, and the fit stayed consistent through long walks.',
    ], $overrides);
}

test('product with no reviews shows a safe empty review state', function () {
    Carbon::setTestNow('2026-05-11 12:00:00');
    $product = reviewProductFixture();

    $this->get(route('storefront.catalog.products.show', $product))
        ->assertOk()
        ->assertSeeText('No reviews yet')
        ->assertDontSeeText('(0 reviews)');

    $this->get(route('storefront.shop'))
        ->assertOk()
        ->assertSeeText('No reviews yet');

    Carbon::setTestNow();
});

test('verified purchaser can submit a product review and aggregates update correctly', function () {
    $user = reviewCustomer();
    $product = reviewProductFixture();
    $orderItem = completedPaidOrderItem($user, $product);

    $this->actingAs($user)
        ->post(route('storefront.catalog.products.reviews.store', $product), reviewPayload())
        ->assertRedirect(route('storefront.catalog.products.show', $product).'#reviews');

    $review = ProductReview::query()->firstOrFail();
    $product->refresh();

    expect($review->product_id)->toBe($product->id)
        ->and($review->user_id)->toBe($user->id)
        ->and($review->order_item_id)->toBe($orderItem->id)
        ->and($review->is_verified_purchase)->toBeTrue()
        ->and($product->review_count)->toBe(1)
        ->and((float) $product->rating_average)->toBe(5.0);
});

test('non purchaser cannot submit a product review', function () {
    $user = reviewCustomer();
    $product = reviewProductFixture();

    $this->from(route('storefront.catalog.products.show', $product))
        ->actingAs($user)
        ->post(route('storefront.catalog.products.reviews.store', $product), reviewPayload())
        ->assertRedirect(route('storefront.catalog.products.show', $product))
        ->assertSessionHasErrors(['review']);

    expect(ProductReview::count())->toBe(0);
});

test('product review rating must be between one and five and payload must be valid', function () {
    $user = reviewCustomer();
    $product = reviewProductFixture();
    completedPaidOrderItem($user, $product);

    $this->from(route('storefront.catalog.products.show', $product))
        ->actingAs($user)
        ->post(route('storefront.catalog.products.reviews.store', $product), reviewPayload([
            'rating' => 7,
            'body' => 'too short',
        ]))
        ->assertRedirect(route('storefront.catalog.products.show', $product))
        ->assertSessionHasErrorsIn('review', ['rating', 'body']);

    expect(ProductReview::count())->toBe(0);
});

test('customer cannot edit or delete another customers review', function () {
    $owner = reviewCustomer();
    $intruder = reviewCustomer();
    $product = reviewProductFixture();
    $orderItem = completedPaidOrderItem($owner, $product);

    $review = ProductReview::factory()->verified($orderItem)->create([
        'rating' => 4,
        'body' => 'Great support and cushioning with a slightly snug forefoot on the first wear.',
    ]);

    $this->actingAs($intruder)
        ->put(route('storefront.catalog.products.reviews.update', [$product, $review]), reviewPayload())
        ->assertForbidden();

    $this->actingAs($intruder)
        ->delete(route('storefront.catalog.products.reviews.destroy', [$product, $review]))
        ->assertForbidden();

    expect($review->fresh())->not->toBeNull();
});

test('duplicate product reviews are prevented per customer per product', function () {
    $user = reviewCustomer();
    $product = reviewProductFixture();
    $orderItem = completedPaidOrderItem($user, $product);

    ProductReview::factory()->verified($orderItem)->create([
        'rating' => 4,
        'body' => 'Dependable cushioning, strong traction, and a clean upper that still feels premium after daily wear.',
    ]);

    $this->from(route('storefront.catalog.products.show', $product))
        ->actingAs($user)
        ->post(route('storefront.catalog.products.reviews.store', $product), reviewPayload())
        ->assertRedirect(route('storefront.catalog.products.show', $product))
        ->assertSessionHasErrors(['review']);

    expect(ProductReview::count())->toBe(1);
});

test('customer can update and delete their own product review', function () {
    $user = reviewCustomer();
    $product = reviewProductFixture();
    $orderItem = completedPaidOrderItem($user, $product);

    $review = ProductReview::factory()->verified($orderItem)->create([
        'rating' => 4,
        'title' => 'Solid first impression',
        'body' => 'Comfortable platform with dependable grip and enough support for long daily wear sessions.',
    ]);

    $this->actingAs($user)
        ->put(route('storefront.catalog.products.reviews.update', [$product, $review]), reviewPayload([
            'rating' => 5,
            'title' => 'Even better after more wear',
            'body' => 'After more use, the cushioning stayed stable, the upper softened nicely, and the traction remained consistent.',
        ]))
        ->assertRedirect(route('storefront.catalog.products.show', $product).'#reviews');

    expect($review->fresh()->rating)->toBe(5)
        ->and($review->fresh()->title)->toBe('Even better after more wear')
        ->and($review->fresh()->body)->toContain('traction remained consistent');

    $this->actingAs($user)
        ->delete(route('storefront.catalog.products.reviews.destroy', [$product, $review]))
        ->assertRedirect(route('storefront.catalog.products.show', $product).'#reviews');

    expect(ProductReview::count())->toBe(0)
        ->and($product->fresh()->review_count)->toBe(0)
        ->and((float) $product->fresh()->rating_average)->toBe(0.0);
});

test('product rating breakdown ignores hidden reviews and deleted reviews update aggregates', function () {
    $firstUser = reviewCustomer();
    $secondUser = reviewCustomer();
    $thirdUser = reviewCustomer();
    $product = reviewProductFixture();

    $fiveStar = ProductReview::factory()->verified(completedPaidOrderItem($firstUser, $product))->create([
        'rating' => 5,
        'body' => 'Excellent comfort, secure heel hold, and very balanced cushioning for daily use.',
    ]);

    ProductReview::factory()->verified(completedPaidOrderItem($secondUser, $product))->create([
        'rating' => 4,
        'body' => 'Responsive ride with enough softness for long sessions and a reliable outsole.',
    ]);

    ProductReview::factory()->verified(completedPaidOrderItem($thirdUser, $product))->hidden()->create([
        'rating' => 1,
        'body' => 'This hidden moderation case should never affect storefront aggregates or visible totals.',
    ]);

    $product->refresh();
    $breakdown = app(ProductReviewAggregateService::class)->breakdownFor($product)->keyBy('rating');

    expect($product->review_count)->toBe(2)
        ->and((float) $product->rating_average)->toBe(4.5)
        ->and($breakdown[5]['count'])->toBe(1)
        ->and($breakdown[4]['count'])->toBe(1)
        ->and($breakdown[1]['count'])->toBe(0);

    $fiveStar->delete();
    $product->refresh();

    expect($product->review_count)->toBe(1)
        ->and((float) $product->rating_average)->toBe(4.0);
});

test('product badge logic stays consistent for new and sale states', function () {
    Carbon::setTestNow('2026-05-11 12:00:00');

    $newSaleProduct = reviewProductFixture([
        'slug' => 'new-sale-product',
        'created_at' => Carbon::parse('2026-05-02 12:00:00'),
        'updated_at' => Carbon::parse('2026-05-02 12:00:00'),
        'base_price' => 4990,
        'compare_at_price' => 5990,
    ]);

    $oldFullPriceProduct = reviewProductFixture([
        'slug' => 'old-full-price-product',
        'created_at' => Carbon::parse('2025-12-15 12:00:00'),
        'updated_at' => Carbon::parse('2025-12-15 12:00:00'),
        'base_price' => 4990,
        'compare_at_price' => 4990,
    ]);

    expect($newSaleProduct->shows_new_badge)->toBeTrue()
        ->and($newSaleProduct->shows_sale_badge)->toBeTrue()
        ->and($oldFullPriceProduct->shows_new_badge)->toBeFalse()
        ->and($oldFullPriceProduct->shows_sale_badge)->toBeFalse();

    Carbon::setTestNow();
});

test('product rating summary only appears when real visible reviews exist', function () {
    $product = reviewProductFixture();

    $this->get(route('storefront.shop'))
        ->assertOk()
        ->assertSeeText('No reviews yet');

    $user = reviewCustomer();
    $review = ProductReview::factory()->verified(completedPaidOrderItem($user, $product))->create([
        'rating' => 5,
        'body' => 'The ride is smooth, the upper feels breathable, and the fit stayed reliable all day.',
    ]);

    $shop = $this->get(route('storefront.shop'))->assertOk();

    $shop->assertSeeText(number_format((float) $review->fresh()->product->rating_average, 1))
        ->assertSeeText('(1)');
});

test('product review api fields stay consistent with storefront rating state', function () {
    Carbon::setTestNow('2026-05-11 12:00:00');
    $product = reviewProductFixture();

    $this->getJson(route('api.v1.catalog.products.show', $product))
        ->assertOk()
        ->assertJsonPath('data.rating_average', null)
        ->assertJsonPath('data.review_count', 0)
        ->assertJsonPath('data.rating_display_state', 'no_reviews')
        ->assertJsonPath('data.shows_rating_summary', false)
        ->assertJsonPath('data.shows_new_badge', true)
        ->assertJsonPath('data.shows_sale_badge', true);

    Carbon::setTestNow();
});

test('product review comments render escaped output', function () {
    $user = reviewCustomer();
    $product = reviewProductFixture();

    ProductReview::factory()->verified(completedPaidOrderItem($user, $product))->create([
        'title' => '<b>Unsafe</b>',
        'body' => '<script>alert("xss")</script>Excellent support with clean transitions and a very stable platform.',
    ]);

    $response = $this->get(route('storefront.catalog.products.show', $product))
        ->assertOk();

    $response->assertDontSee('<script>alert("xss")</script>', escape: false)
        ->assertSee('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;Excellent support with clean transitions and a very stable platform.', escape: false);
});
