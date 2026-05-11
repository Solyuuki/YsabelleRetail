<?php

use App\Models\Access\Role;
use App\Models\Cart\Cart;
use App\Models\Catalog\ProductVariant;
use App\Models\Orders\Order;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createShippingPersistenceCustomer(array $attributes = []): User
{
    $customerRole = Role::query()->firstOrCreate(
        ['slug' => 'customer'],
        [
            'name' => 'Customer',
            'description' => 'Customer role',
            'is_system' => true,
        ],
    );

    $user = User::factory()->create($attributes);
    $user->roles()->attach($customerRole);

    return $user;
}

function seedShippingPersistenceCart(User $user): Cart
{
    $variant = ProductVariant::factory()->create([
        'price' => 1899,
    ]);

    $variant->inventoryItem()->create([
        'quantity_on_hand' => 12,
        'reserved_quantity' => 0,
        'reorder_level' => 2,
        'allow_backorder' => false,
    ]);

    $cart = Cart::query()->firstOrCreate(
        [
            'user_id' => $user->id,
            'status' => 'active',
        ],
        [
            'currency' => 'PHP',
            'expires_at' => now()->addDays(7),
        ],
    );

    $cart->items()->delete();

    $cart->items()->create([
        'product_variant_id' => $variant->id,
        'quantity' => 1,
        'unit_price' => 1899,
        'line_total' => 1899,
        'metadata' => [
            'product_slug' => $variant->product->slug,
        ],
    ]);

    return $cart->fresh(['items.variant.product']);
}

function createReusableShippingProfile(User $user, array $overrides = []): UserProfile
{
    return $user->profile()->create(array_merge([
        'preferred_name' => $user->name,
        'phone' => null,
        'mobile_number' => null,
        'shipping_city' => 'Pasig',
        'shipping_address_line' => '88 Emerald Avenue',
        'shipping_postal_code' => '1605',
    ], $overrides));
}

function shippingCheckoutPayload(array $overrides = []): array
{
    return array_merge([
        'full_name' => 'Test Customer',
        'email' => 'customer@example.com',
        'phone' => '09171234567',
        'city' => 'Makati',
        'address' => '123 Test Street',
        'postal_code' => '1200',
        'order_notes' => 'Leave at the lobby.',
        'payment_method' => 'cod',
    ], $overrides);
}

test('checkout page pre-fills saved profile shipping details', function () {
    $user = createShippingPersistenceCustomer([
        'name' => 'Checkout Customer',
        'email' => 'MixedCaseCustomer@Example.com',
    ]);

    seedShippingPersistenceCart($user);
    createReusableShippingProfile($user, [
        'phone' => '0281234567',
        'mobile_number' => null,
    ]);

    $this->actingAs($user)
        ->get(route('storefront.checkout.create'))
        ->assertOk()
        ->assertSee('value="Checkout Customer"', escape: false)
        ->assertSee('value="mixedcasecustomer@example.com"', escape: false)
        ->assertSee('value="0281234567"', escape: false)
        ->assertSee('value="Pasig"', escape: false)
        ->assertSee('value="88 Emerald Avenue"', escape: false)
        ->assertSee('value="1605"', escape: false);
});

test('first successful checkout saves reusable shipping details', function () {
    $user = createShippingPersistenceCustomer();
    seedShippingPersistenceCart($user);

    $this->actingAs($user)
        ->post(route('storefront.checkout.store'), shippingCheckoutPayload([
            'full_name' => '  Test Customer  ',
            'email' => '  CUSTOMER@EXAMPLE.COM  ',
            'phone' => '  09171234567  ',
            'city' => '  Makati  ',
            'address' => '  123 Test Street  ',
            'postal_code' => '  1200  ',
        ]))
        ->assertRedirect(route('storefront.account.index'));

    $profile = $user->fresh()->profile;
    $order = Order::query()->latest('id')->firstOrFail();

    expect($profile)->not->toBeNull()
        ->and($profile->mobile_number)->toBe('09171234567')
        ->and($profile->shipping_city)->toBe('Makati')
        ->and($profile->shipping_address_line)->toBe('123 Test Street')
        ->and($profile->shipping_postal_code)->toBe('1200')
        ->and($order->customer_name)->toBe('Test Customer')
        ->and($order->customer_email)->toBe('customer@example.com')
        ->and($order->customer_phone)->toBe('09171234567')
        ->and($order->shipping_city)->toBe('Makati')
        ->and($order->shipping_address_line)->toBe('123 Test Street')
        ->and($order->shipping_postal_code)->toBe('1200');
});

test('next checkout auto-fills saved shipping details after a successful checkout', function () {
    $user = createShippingPersistenceCustomer();
    seedShippingPersistenceCart($user);

    $this->actingAs($user)
        ->post(route('storefront.checkout.store'), shippingCheckoutPayload([
            'phone' => '09179998888',
            'city' => 'Taguig',
            'address' => '77 Market Street',
            'postal_code' => '1630',
        ]))
        ->assertRedirect(route('storefront.account.index'));

    seedShippingPersistenceCart($user);

    $this->actingAs($user)
        ->get(route('storefront.checkout.create'))
        ->assertOk()
        ->assertSee('value="09179998888"', escape: false)
        ->assertSee('value="Taguig"', escape: false)
        ->assertSee('value="77 Market Street"', escape: false)
        ->assertSee('value="1630"', escape: false);
});

test('edited checkout details update saved reusable details only after successful checkout', function () {
    $user = createShippingPersistenceCustomer();
    seedShippingPersistenceCart($user);
    createReusableShippingProfile($user, [
        'mobile_number' => '09170000001',
        'shipping_city' => 'Pasig',
        'shipping_address_line' => '88 Emerald Avenue',
        'shipping_postal_code' => '1605',
    ]);

    $this->actingAs($user)
        ->post(route('storefront.checkout.store'), shippingCheckoutPayload([
            'phone' => '09179990000',
            'city' => 'Quezon City',
            'address' => '55 Scout Circle',
            'postal_code' => '1103',
        ]))
        ->assertRedirect(route('storefront.account.index'));

    $profile = $user->fresh()->profile;

    expect($profile->mobile_number)->toBe('09179990000')
        ->and($profile->shipping_city)->toBe('Quezon City')
        ->and($profile->shipping_address_line)->toBe('55 Scout Circle')
        ->and($profile->shipping_postal_code)->toBe('1103');

    seedShippingPersistenceCart($user);

    $this->actingAs($user)
        ->get(route('storefront.checkout.create'))
        ->assertOk()
        ->assertSee('value="09179990000"', escape: false)
        ->assertSee('value="Quezon City"', escape: false)
        ->assertSee('value="55 Scout Circle"', escape: false)
        ->assertSee('value="1103"', escape: false);
});

test('failed validation does not overwrite saved profile details', function () {
    $user = createShippingPersistenceCustomer();
    seedShippingPersistenceCart($user);
    createReusableShippingProfile($user, [
        'mobile_number' => '09170000001',
        'shipping_city' => 'Pasig',
        'shipping_address_line' => '88 Emerald Avenue',
        'shipping_postal_code' => '1605',
    ]);

    $this->from(route('storefront.checkout.create'))
        ->actingAs($user)
        ->post(route('storefront.checkout.store'), shippingCheckoutPayload([
            'phone' => '09179990000',
            'city' => '   ',
            'address' => '55 Scout Circle',
            'postal_code' => '1103',
        ]))
        ->assertRedirect(route('storefront.checkout.create'))
        ->assertSessionHasErrors(['city']);

    $profile = $user->fresh()->profile;

    expect($profile->mobile_number)->toBe('09170000001')
        ->and($profile->shipping_city)->toBe('Pasig')
        ->and($profile->shipping_address_line)->toBe('88 Emerald Avenue')
        ->and($profile->shipping_postal_code)->toBe('1605');
});

test('order notes are stored on the order but are not reused on the next checkout', function () {
    $user = createShippingPersistenceCustomer();
    seedShippingPersistenceCart($user);

    $this->actingAs($user)
        ->post(route('storefront.checkout.store'), shippingCheckoutPayload([
            'order_notes' => 'Leave at guard desk.',
        ]))
        ->assertRedirect(route('storefront.account.index'));

    $order = Order::query()->latest('id')->firstOrFail();

    expect($order->notes)->toBe('Leave at guard desk.');

    seedShippingPersistenceCart($user);

    $this->actingAs($user)
        ->get(route('storefront.checkout.create'))
        ->assertOk()
        ->assertDontSee('Leave at guard desk.');
});

test('another user cannot see or reuse the first users saved shipping details', function () {
    $firstUser = createShippingPersistenceCustomer([
        'name' => 'First Customer',
        'email' => 'first@example.com',
    ]);
    seedShippingPersistenceCart($firstUser);
    createReusableShippingProfile($firstUser, [
        'mobile_number' => '09175550000',
        'shipping_city' => 'Pasig',
        'shipping_address_line' => 'First Address',
        'shipping_postal_code' => '1600',
    ]);

    $secondUser = createShippingPersistenceCustomer([
        'name' => 'Second Customer',
        'email' => 'second@example.com',
    ]);
    seedShippingPersistenceCart($secondUser);

    $this->actingAs($secondUser)
        ->get(route('storefront.checkout.create'))
        ->assertOk()
        ->assertSee('value="Second Customer"', escape: false)
        ->assertSee('value="second@example.com"', escape: false)
        ->assertDontSee('value="09175550000"', escape: false)
        ->assertDontSee('value="Pasig"', escape: false)
        ->assertDontSee('value="First Address"', escape: false)
        ->assertDontSee('value="1600"', escape: false);

    $this->actingAs($secondUser)
        ->post(route('storefront.checkout.store'), shippingCheckoutPayload([
            'phone' => '09176660000',
            'city' => 'Cainta',
            'address' => 'Second Address',
            'postal_code' => '1900',
        ]))
        ->assertRedirect(route('storefront.account.index'));

    expect($firstUser->fresh()->profile->mobile_number)->toBe('09175550000')
        ->and($firstUser->fresh()->profile->shipping_city)->toBe('Pasig')
        ->and($secondUser->fresh()->profile->mobile_number)->toBe('09176660000')
        ->and($secondUser->fresh()->profile->shipping_city)->toBe('Cainta');
});

test('guest checkout remains blocked', function () {
    $this->get(route('storefront.checkout.create'))
        ->assertRedirect(route('login'));

    $this->post(route('storefront.checkout.store'), shippingCheckoutPayload())
        ->assertRedirect(route('login'));
});
