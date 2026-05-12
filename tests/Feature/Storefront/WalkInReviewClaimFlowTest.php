<?php

use App\Models\Access\Role;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductReview;
use App\Models\Catalog\ProductVariant;
use App\Models\Orders\Order;
use App\Models\Orders\OrderReviewClaim;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function walkInReviewClaimCustomer(array $attributes = []): User
{
    $role = Role::query()->firstOrCreate(
        ['slug' => 'customer'],
        ['name' => 'Customer', 'description' => 'Customer role', 'is_system' => true],
    );

    $user = User::factory()->create($attributes);
    $user->roles()->attach($role);

    return $user;
}

function walkInReviewClaimProduct(array $overrides = []): Product
{
    $category = Category::factory()->create([
        'name' => 'Walk-In Reviews '.random_int(100, 999),
        'slug' => 'walk-in-reviews-'.random_int(1000, 9999),
        'is_active' => true,
    ]);

    $product = Product::factory()->for($category)->create(array_merge([
        'name' => 'Claimable Runner '.random_int(100, 999),
        'slug' => 'claimable-runner-'.random_int(1000, 9999),
        'status' => 'active',
        'base_price' => 5290,
        'compare_at_price' => 5990,
    ], $overrides));

    $variant = ProductVariant::factory()->for($product)->create([
        'name' => 'Size 9',
        'sku' => 'YS-CLAIM-'.random_int(1000, 9999),
        'option_values' => ['size' => '9', 'color' => 'Black/Gold'],
        'status' => 'active',
        'price' => 5290,
    ]);

    $variant->inventoryItem()->create([
        'quantity_on_hand' => 8,
        'reserved_quantity' => 0,
        'reorder_level' => 2,
        'allow_backorder' => false,
    ]);

    return $product->fresh(['category', 'variants.inventoryItem']);
}

function walkInReviewClaimOrder(Product $product, string $email): Order
{
    $variant = $product->variants()->firstOrFail();

    return tap(Order::query()->create([
        'user_id' => null,
        'source' => 'walk_in',
        'handled_by_user_id' => null,
        'order_number' => 'YSP-CLAIM-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
        'status' => 'completed',
        'payment_status' => 'paid',
        'fulfillment_status' => 'fulfilled',
        'currency' => 'PHP',
        'subtotal_amount' => 5290,
        'discount_amount' => 0,
        'shipping_amount' => 0,
        'tax_amount' => 0,
        'grand_total' => 5290,
        'placed_at' => now(),
        'customer_name' => 'Walk-in Customer',
        'customer_email' => $email,
        'payment_method' => 'cash',
        'metadata' => ['walk_in' => true],
    ]), function (Order $order) use ($product, $variant): void {
        $order->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'variant_name' => $variant->name,
            'sku' => $variant->sku,
            'quantity' => 1,
            'unit_price' => 5290,
            'line_total' => 5290,
        ]);
    })->fresh(['items', 'reviewClaim']);
}

function walkInReviewClaimRecord(Order $order, string $plainToken, ?Carbon $expiresAt = null): OrderReviewClaim
{
    return OrderReviewClaim::query()->create([
        'order_id' => $order->id,
        'claimed_by_user_id' => null,
        'customer_email' => $order->customer_email,
        'token_hash' => hash('sha256', $plainToken),
        'expires_at' => $expiresAt ?? now()->addDays(30),
        'sent_at' => now(),
        'used_at' => null,
    ]);
}

function walkInReviewClaimPayload(array $overrides = []): array
{
    return array_merge([
        'rating' => 5,
        'title' => 'Verified after store visit',
        'body' => 'The fit stayed consistent, the cushioning felt stable, and the in-store purchase claim worked smoothly.',
    ], $overrides);
}

test('walk in review claim can attach a paid completed walk in order to an existing customer and unlock verified reviews', function () {
    $user = walkInReviewClaimCustomer([
        'email' => 'walker@example.com',
    ]);
    $product = walkInReviewClaimProduct();
    $order = walkInReviewClaimOrder($product, 'walker@example.com');
    $token = str_repeat('a', 64);
    walkInReviewClaimRecord($order, $token);

    $this->get(route('storefront.account.review-claims.show', ['token' => $token]))
        ->assertOk()
        ->assertSeeText('Sign in to claim')
        ->assertSeeText($order->order_number);

    $this->actingAs($user)
        ->get(route('storefront.account.review-claims.show', ['token' => $token]))
        ->assertOk()
        ->assertSeeText('Confirm purchase claim');

    $this->actingAs($user)
        ->post(route('storefront.account.review-claims.store', ['token' => $token]))
        ->assertRedirect(route('storefront.account.index'));

    $order->refresh();
    $claim = $order->reviewClaim()->firstOrFail()->fresh();

    expect($order->user_id)->toBe($user->id)
        ->and($claim->claimed_by_user_id)->toBe($user->id)
        ->and($claim->used_at)->not->toBeNull();

    $this->actingAs($user)
        ->post(route('storefront.catalog.products.reviews.store', $product), walkInReviewClaimPayload())
        ->assertRedirect(route('storefront.catalog.products.show', $product).'#reviews');

    $review = ProductReview::query()->firstOrFail();

    expect($review->user_id)->toBe($user->id)
        ->and($review->orderItem?->order_id)->toBe($order->id)
        ->and($review->is_verified_purchase)->toBeTrue();
});

test('walk in review claim supports sign up with intended redirect before the claim is confirmed', function () {
    $product = walkInReviewClaimProduct();
    $order = walkInReviewClaimOrder($product, 'new.walkin@example.com');
    $token = str_repeat('b', 64);
    walkInReviewClaimRecord($order, $token);
    $claimUrl = route('storefront.account.review-claims.show', ['token' => $token]);

    $response = $this->get($claimUrl)
        ->assertOk()
        ->assertSee(route('register', ['intended' => $claimUrl], false));

    $response->assertSeeText('Create account with this email');
});

test('walk in review claim rejects wrong users expired links and duplicate reuse', function () {
    $rightUser = walkInReviewClaimCustomer([
        'email' => 'claim.right@example.com',
    ]);
    $wrongUser = walkInReviewClaimCustomer([
        'email' => 'claim.wrong@example.com',
    ]);
    $product = walkInReviewClaimProduct();

    $mismatchOrder = walkInReviewClaimOrder($product, 'claim.right@example.com');
    $mismatchToken = str_repeat('c', 64);
    walkInReviewClaimRecord($mismatchOrder, $mismatchToken);

    $this->actingAs($wrongUser)
        ->get(route('storefront.account.review-claims.show', ['token' => $mismatchToken]))
        ->assertOk()
        ->assertSeeText('This claim only works with the email address that received the purchase email.');

    $this->from(route('storefront.account.review-claims.show', ['token' => $mismatchToken]))
        ->actingAs($wrongUser)
        ->post(route('storefront.account.review-claims.store', ['token' => $mismatchToken]))
        ->assertRedirect(route('storefront.account.review-claims.show', ['token' => $mismatchToken]))
        ->assertSessionHasErrors(['claim']);

    $expiredOrder = walkInReviewClaimOrder($product, 'claim.right@example.com');
    $expiredToken = str_repeat('d', 64);
    walkInReviewClaimRecord($expiredOrder, $expiredToken, now()->subHour());

    $this->actingAs($rightUser)
        ->get(route('storefront.account.review-claims.show', ['token' => $expiredToken]))
        ->assertOk()
        ->assertSeeText('This claim link has expired.');

    $this->from(route('storefront.account.review-claims.show', ['token' => $expiredToken]))
        ->actingAs($rightUser)
        ->post(route('storefront.account.review-claims.store', ['token' => $expiredToken]))
        ->assertRedirect(route('storefront.account.review-claims.show', ['token' => $expiredToken]))
        ->assertSessionHasErrors(['claim']);

    $claimableOrder = walkInReviewClaimOrder($product, 'claim.right@example.com');
    $claimableToken = str_repeat('e', 64);
    walkInReviewClaimRecord($claimableOrder, $claimableToken);

    $this->actingAs($rightUser)
        ->post(route('storefront.account.review-claims.store', ['token' => $claimableToken]))
        ->assertRedirect(route('storefront.account.index'));

    $this->actingAs($rightUser)
        ->get(route('storefront.account.review-claims.show', ['token' => $claimableToken]))
        ->assertOk()
        ->assertSeeText('This purchase has already been claimed.');

    $this->from(route('storefront.account.review-claims.show', ['token' => $claimableToken]))
        ->actingAs($rightUser)
        ->post(route('storefront.account.review-claims.store', ['token' => $claimableToken]))
        ->assertRedirect(route('storefront.account.review-claims.show', ['token' => $claimableToken]))
        ->assertSessionHasErrors(['claim']);
});
