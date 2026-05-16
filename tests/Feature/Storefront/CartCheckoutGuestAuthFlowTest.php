<?php

use App\Models\Access\Role;
use App\Models\Cart\Cart;
use App\Models\Catalog\ProductVariant;
use App\Models\User;
use App\Services\Storefront\CartService;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function cartGuestSessionKey(): string
{
    return 'storefront.cart_guest_session_id';
}

function ensureCartCheckoutCustomerRole(): void
{
    Role::query()->firstOrCreate(
        ['slug' => 'customer'],
        [
            'name' => 'Customer',
            'description' => 'Customer role',
            'is_system' => true,
        ],
    );
}

function createCartCheckoutCustomer(array $attributes = []): User
{
    ensureCartCheckoutCustomerRole();

    $user = User::factory()->create($attributes);
    $user->roles()->attach(Role::query()->where('slug', 'customer')->firstOrFail());

    return $user;
}

function createCartCheckoutVariant(array $overrides = [], int $stock = 12): ProductVariant
{
    $variant = ProductVariant::factory()->create(array_merge([
        'price' => 1899,
        'status' => 'active',
        'option_values' => [
            'size' => '8',
            'color' => 'Black',
        ],
    ], $overrides));

    $variant->inventoryItem()->create([
        'quantity_on_hand' => $stock,
        'reserved_quantity' => 0,
        'reorder_level' => 2,
        'allow_backorder' => false,
    ]);

    return $variant->fresh(['product', 'inventoryItem']);
}

function createGuestCartForSession(string $sessionId, array $items): Cart
{
    $cart = Cart::query()->create([
        'session_id' => $sessionId,
        'status' => 'active',
        'currency' => 'PHP',
        'expires_at' => now()->addDays(7),
    ]);

    foreach ($items as $item) {
        $variant = $item['variant'];
        $quantity = $item['quantity'];
        $unitPrice = $item['unit_price'] ?? $variant->price ?? $variant->product->base_price;

        $cart->items()->create([
            'product_variant_id' => $variant->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $quantity * $unitPrice,
            'metadata' => [
                'product_slug' => $variant->product->slug,
            ],
        ]);
    }

    return $cart->fresh(['items.variant.product']);
}

function createUserCart(User $user, array $items): Cart
{
    $cart = Cart::query()->create([
        'user_id' => $user->id,
        'status' => 'active',
        'currency' => 'PHP',
        'expires_at' => now()->addDays(7),
    ]);

    foreach ($items as $item) {
        $variant = $item['variant'];
        $quantity = $item['quantity'];
        $unitPrice = $item['unit_price'] ?? $variant->price ?? $variant->product->base_price;

        $cart->items()->create([
            'product_variant_id' => $variant->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $quantity * $unitPrice,
            'metadata' => [
                'product_slug' => $variant->product->slug,
            ],
        ]);
    }

    return $cart->fresh(['items.variant.product']);
}

test('cart page sign in button routes guests through login with checkout intent', function () {
    $variant = createCartCheckoutVariant();
    $cart = createGuestCartForSession('guest-cart-cta-session', [
        ['variant' => $variant, 'quantity' => 1],
    ]);
    $cart->load(['items.variant.product.category', 'items.variant.inventoryItem']);
    $summary = [
        'cart' => $cart,
        'items' => $cart->items,
        'item_count' => 1,
        'subtotal' => 1899.0,
        'shipping' => 350.0,
        'total' => 2249.0,
        'is_empty' => false,
        'has_inventory_issues' => false,
        'inventory_issues' => collect(),
    ];

    $this->mock(CartService::class, function ($mock) use ($summary): void {
        $mock->shouldReceive('summary')->once()->andReturn($summary);
        $mock->shouldReceive('itemCount')->andReturn(1);
    });

    $this->get(route('storefront.cart.index'))
        ->assertOk()
        ->assertSee('Sign in to checkout')
        ->assertSee(
            'href="'.route('login', ['intended' => route('storefront.checkout.create')]).'"',
            escape: false,
        );
});

test('guest cart survives login and merges into the authenticated cart without overwriting existing items', function () {
    $customer = createCartCheckoutCustomer([
        'email' => 'guest.merge.login@example.com',
        'password' => 'Password123x',
    ]);

    $sharedVariant = createCartCheckoutVariant([
        'price' => 1999,
        'sku' => 'YS-CART-MERGE-SHARED',
    ], stock: 4);
    $guestOnlyVariant = createCartCheckoutVariant([
        'price' => 2499,
        'sku' => 'YS-CART-MERGE-GUEST',
        'option_values' => [
            'size' => '9',
            'color' => 'Ivory',
        ],
    ], stock: 8);
    $userOnlyVariant = createCartCheckoutVariant([
        'price' => 1599,
        'sku' => 'YS-CART-MERGE-USER',
        'option_values' => [
            'size' => '7',
            'color' => 'Sand',
        ],
    ], stock: 8);

    $guestSessionId = 'guest-login-merge-session';
    createGuestCartForSession($guestSessionId, [
        ['variant' => $sharedVariant, 'quantity' => 3],
        ['variant' => $guestOnlyVariant, 'quantity' => 2],
    ]);
    createUserCart($customer, [
        ['variant' => $sharedVariant, 'quantity' => 1],
        ['variant' => $userOnlyVariant, 'quantity' => 2],
    ]);

    $this->withSession([
        cartGuestSessionKey() => $guestSessionId,
    ])->post(route('login.store'), [
        'email' => $customer->email,
        'password' => 'Password123x',
        'intended' => route('storefront.checkout.create'),
    ])->assertRedirect(route('storefront.checkout.create'));

    $cart = Cart::query()
        ->where('user_id', $customer->id)
        ->where('status', 'active')
        ->firstOrFail()
        ->load('items');

    $sharedItem = $cart->items->firstWhere('product_variant_id', $sharedVariant->id);
    $guestOnlyItem = $cart->items->firstWhere('product_variant_id', $guestOnlyVariant->id);
    $userOnlyItem = $cart->items->firstWhere('product_variant_id', $userOnlyVariant->id);

    expect($cart->session_id)->toBeNull()
        ->and($cart->items)->toHaveCount(3)
        ->and((int) $sharedItem->quantity)->toBe(4)
        ->and((float) $sharedItem->unit_price)->toBe(1999.0)
        ->and((float) $sharedItem->line_total)->toBe(7996.0)
        ->and((int) $guestOnlyItem->quantity)->toBe(2)
        ->and((int) $userOnlyItem->quantity)->toBe(2)
        ->and(Cart::query()->where('status', 'active')->count())->toBe(1);

    $this->assertDatabaseMissing('carts', [
        'session_id' => $guestSessionId,
        'status' => 'active',
    ]);
});

test('guest cart survives register and is reassigned to the new customer before checkout', function () {
    ensureCartCheckoutCustomerRole();
    Notification::fake();

    $variant = createCartCheckoutVariant([
        'price' => 2899,
        'sku' => 'YS-CART-REGISTER',
    ], stock: 6);

    $guestSessionId = 'guest-register-session';
    $guestCart = createGuestCartForSession($guestSessionId, [
        ['variant' => $variant, 'quantity' => 2],
    ]);

    $this->withSession([
        cartGuestSessionKey() => $guestSessionId,
    ])->post(route('register.store'), [
        'name' => 'Guest Cart Register Customer',
        'email' => 'guest.cart.register@example.com',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
        'intended' => route('storefront.checkout.create'),
    ])
        ->assertRedirect(route('storefront.checkout.create'))
        ->assertSessionHas('status', 'We sent a verification link to your email address.');

    $user = User::query()->where('email', 'guest.cart.register@example.com')->firstOrFail();
    $cart = Cart::query()->findOrFail($guestCart->id)->load('items');
    $line = $cart->items->firstWhere('product_variant_id', $variant->id);

    expect($cart->user_id)->toBe($user->id)
        ->and($cart->session_id)->toBeNull()
        ->and($cart->items)->toHaveCount(1)
        ->and((int) $line->quantity)->toBe(2)
        ->and((float) $line->unit_price)->toBe(2899.0)
        ->and((float) $line->line_total)->toBe(5798.0);

    $this->assertDatabaseMissing('carts', [
        'session_id' => $guestSessionId,
        'status' => 'active',
    ]);
    $this->assertAuthenticatedAs($user);
    Notification::assertSentTo($user, VerifyEmail::class);
});
