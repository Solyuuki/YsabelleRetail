<?php

use App\Models\Access\Role;
use App\Models\Cart\Cart;
use App\Models\Catalog\ProductVariant;
use App\Models\Orders\Order;
use App\Models\Payments\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createCheckoutCustomer(): User
{
    $customerRole = Role::query()->create([
        'name' => 'Customer',
        'slug' => 'customer',
        'description' => 'Customer role',
        'is_system' => true,
    ]);

    $user = User::factory()->create();
    $user->roles()->attach($customerRole);

    return $user;
}

function seedCheckoutCart(User $user): Cart
{
    $variant = ProductVariant::factory()->create([
        'price' => 1899,
    ]);

    $cart = Cart::query()->create([
        'user_id' => $user->id,
        'status' => 'active',
        'currency' => 'PHP',
        'expires_at' => now()->addDays(7),
    ]);

    $cart->items()->create([
        'product_variant_id' => $variant->id,
        'quantity' => 2,
        'unit_price' => 1899,
        'line_total' => 3798,
        'metadata' => [
            'product_slug' => $variant->product->slug,
        ],
    ]);

    return $cart->fresh(['items.variant.product']);
}

function checkoutPayload(array $overrides = []): array
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

test('checkout page includes the simulated card section and card payment option', function () {
    $user = createCheckoutCustomer();
    seedCheckoutCart($user);

    $response = $this->actingAs($user)
        ->get(route('storefront.checkout.create'))
        ->assertOk()
        ->assertSee('value="cod"', escape: false)
        ->assertSee('value="card_simulated"', escape: false)
        ->assertSee('data-card-payment-section', escape: false)
        ->assertSee('Simulated card details')
        ->assertSee('Recommended test card');

    expect($response->getContent())->toMatch('/data-card-payment-section[^>]*hidden/');
});

test('cash on delivery stays unpaid and uses the offline payment flow', function () {
    $user = createCheckoutCustomer();
    seedCheckoutCart($user);

    $this->actingAs($user)
        ->post(route('storefront.checkout.store'), checkoutPayload([
            'payment_method' => 'cod',
        ]))
        ->assertRedirect(route('storefront.account.index'));

    $order = Order::query()->latest('id')->firstOrFail();
    $payment = Payment::query()->latest('id')->firstOrFail();

    expect($order->payment_method)->toBe('cod')
        ->and($order->payment_status)->toBe('unpaid')
        ->and($payment->provider)->toBe('cash-on-delivery')
        ->and($payment->status)->toBe('pending')
        ->and($payment->paid_at)->toBeNull()
        ->and($payment->provider_reference)->toBeNull()
        ->and($payment->metadata['flow'])->toBe('offline-manual')
        ->and($payment->metadata['simulated'])->toBeFalse();
});

test('simulated card checkout shows validation errors instead of falling back to cod', function () {
    $user = createCheckoutCustomer();
    seedCheckoutCart($user);

    $this->from(route('storefront.checkout.create'))
        ->actingAs($user)
        ->post(route('storefront.checkout.store'), checkoutPayload([
            'payment_method' => 'card_simulated',
            'cardholder_name' => '',
            'card_number' => '',
            'card_expiry' => '',
            'card_cvc' => '',
        ]))
        ->assertRedirect(route('storefront.checkout.create'))
        ->assertSessionHasErrors([
            'cardholder_name',
            'card_number',
            'card_expiry',
            'card_cvc',
        ]);

    expect(Order::count())->toBe(0)
        ->and(Payment::count())->toBe(0);
});

test('simulated card checkout marks the order as paid and uses the card flow', function () {
    $user = createCheckoutCustomer();
    seedCheckoutCart($user);

    $this->actingAs($user)
        ->post(route('storefront.checkout.store'), checkoutPayload([
            'payment_method' => 'card_simulated',
            'cardholder_name' => 'Test Customer',
            'card_number' => '4242 4242 4242 4242',
            'card_expiry' => '12/30',
            'card_cvc' => '123',
        ]))
        ->assertRedirect(route('storefront.account.index'));

    $order = Order::query()->latest('id')->firstOrFail();
    $payment = Payment::query()->latest('id')->firstOrFail();

    expect($order->payment_method)->toBe('card_simulated')
        ->and($order->payment_status)->toBe('paid')
        ->and($payment->provider)->toBe('card-simulated')
        ->and($payment->status)->toBe('succeeded')
        ->and($payment->paid_at)->not->toBeNull()
        ->and($payment->provider_reference)->not->toBeNull()
        ->and($payment->metadata['flow'])->toBe('simulated-card')
        ->and($payment->metadata['card_last4'])->toBe('4242')
        ->and($payment->metadata['simulated'])->toBeTrue();
});

test('invalid payment methods are rejected gracefully', function () {
    $user = createCheckoutCustomer();
    seedCheckoutCart($user);

    $this->from(route('storefront.checkout.create'))
        ->actingAs($user)
        ->post(route('storefront.checkout.store'), checkoutPayload([
            'payment_method' => 'bank_transfer',
        ]))
        ->assertRedirect(route('storefront.checkout.create'))
        ->assertSessionHasErrors(['payment_method']);

    expect(Order::count())->toBe(0)
        ->and(Payment::count())->toBe(0);
});
