<?php

use App\Models\Access\Role;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use App\Models\Orders\Order;
use App\Models\Orders\OrderReviewClaim;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function ensureCustomerRoleExists(): void
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

function registerClaimDestinationUrl(string $email): string
{
    $category = Category::factory()->create([
        'name' => 'Register Claim Category',
        'slug' => 'register-claim-category',
        'is_active' => true,
    ]);

    $product = Product::factory()->for($category)->create([
        'name' => 'Register Claim Product',
        'slug' => 'register-claim-product',
        'status' => 'active',
        'base_price' => 4990,
        'compare_at_price' => 5590,
    ]);

    $variant = ProductVariant::factory()->for($product)->create([
        'name' => 'Size 8',
        'sku' => 'YS-REG-CLAIM-001',
        'option_values' => ['size' => '8', 'color' => 'Black'],
        'status' => 'active',
        'price' => 4990,
    ]);

    $variant->inventoryItem()->create([
        'quantity_on_hand' => 6,
        'reserved_quantity' => 0,
        'reorder_level' => 2,
        'allow_backorder' => false,
    ]);

    $order = Order::query()->create([
        'user_id' => null,
        'source' => 'walk_in',
        'handled_by_user_id' => null,
        'order_number' => 'YSP-REG-CLAIM-001',
        'status' => 'completed',
        'payment_status' => 'paid',
        'fulfillment_status' => 'fulfilled',
        'currency' => 'PHP',
        'subtotal_amount' => 4990,
        'discount_amount' => 0,
        'shipping_amount' => 0,
        'tax_amount' => 0,
        'grand_total' => 4990,
        'placed_at' => now(),
        'customer_name' => 'Walk-in Customer',
        'customer_email' => $email,
        'payment_method' => 'cash',
        'metadata' => ['walk_in' => true],
    ]);

    $order->items()->create([
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'product_name' => $product->name,
        'variant_name' => $variant->name,
        'sku' => $variant->sku,
        'quantity' => 1,
        'unit_price' => 4990,
        'line_total' => 4990,
    ]);

    $token = str_repeat('f', 64);

    OrderReviewClaim::query()->create([
        'order_id' => $order->id,
        'claimed_by_user_id' => null,
        'customer_email' => $email,
        'token_hash' => hash('sha256', $token),
        'expires_at' => now()->addDays(30),
        'sent_at' => now(),
        'used_at' => null,
    ]);

    return route('storefront.account.review-claims.show', ['token' => $token]);
}

test('valid registration creates a real customer account', function () {
    ensureCustomerRoleExists();
    Notification::fake();

    $this->from(route('register'))
        ->post(route('register.store'), [
            'name' => 'Ysabelle Shopper',
            'email' => 'shopper@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ])
        ->assertRedirect(route('verification.notice'))
        ->assertSessionHas('status', 'We sent a verification link to your email address.');

    $user = User::query()->where('email', 'shopper@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user?->profile)->not->toBeNull();
    expect($user?->hasRole('customer'))->toBeTrue();
    expect($user?->hasVerifiedEmail())->toBeFalse();

    $this->assertAuthenticatedAs($user);
    Notification::assertSentTo($user, VerifyEmail::class);
});

test('invalid registration payload is rejected', function () {
    ensureCustomerRoleExists();

    $this->from(route('register'))
        ->post(route('register.store'), [
            'name' => 'Y',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])
        ->assertSessionHasErrors(['name', 'email', 'password']);

    $this->assertGuest();
});

test('registration rejects passwords that do not meet the manual auth policy', function () {
    ensureCustomerRoleExists();

    $this->from(route('register'))
        ->post(route('register.store'), [
            'name' => 'Policy Check',
            'email' => 'policy@example.com',
            'password' => 'lowercaseonly',
            'password_confirmation' => 'lowercaseonly',
        ])
        ->assertSessionHasErrors(['password']);

    $this->assertDatabaseMissing('users', [
        'email' => 'policy@example.com',
    ]);
});

test('registration accepts passwords with lowercase letters and numbers only', function () {
    ensureCustomerRoleExists();
    Notification::fake();

    $this->post(route('register.store'), [
        'name' => 'Lowercase Accepted',
        'email' => 'password1@example.com',
        'password' => 'password1',
        'password_confirmation' => 'password1',
    ])->assertRedirect(route('verification.notice'));

    $this->assertDatabaseHas('users', [
        'email' => 'password1@example.com',
    ]);
});

test('registration accepts passwords with uppercase letters and numbers only', function () {
    ensureCustomerRoleExists();
    Notification::fake();

    $this->post(route('register.store'), [
        'name' => 'Uppercase Accepted',
        'email' => 'password-uppercase@example.com',
        'password' => 'PASSWORD1',
        'password_confirmation' => 'PASSWORD1',
    ])->assertRedirect(route('verification.notice'));

    $this->assertDatabaseHas('users', [
        'email' => 'password-uppercase@example.com',
    ]);
});

test('registration accepts ecommerce friendly alphanumeric passwords', function () {
    ensureCustomerRoleExists();
    Notification::fake();

    $this->post(route('register.store'), [
        'name' => 'Retail Accepted',
        'email' => 'retail-password@example.com',
        'password' => 'retail2025',
        'password_confirmation' => 'retail2025',
    ])->assertRedirect(route('verification.notice'));

    $this->assertDatabaseHas('users', [
        'email' => 'retail-password@example.com',
    ]);
});

test('registration rejects passwords without a number', function () {
    ensureCustomerRoleExists();

    $this->from(route('register'))
        ->post(route('register.store'), [
            'name' => 'No Number',
            'email' => 'password-no-number@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertSessionHasErrors([
            'password' => 'The password must be at least 8 characters and include at least one letter and one number.',
        ]);
});

test('registration rejects numeric only passwords', function () {
    ensureCustomerRoleExists();

    $this->from(route('register'))
        ->post(route('register.store'), [
            'name' => 'Numbers Only',
            'email' => 'numbers-only@example.com',
            'password' => '12345678',
            'password_confirmation' => '12345678',
        ])
        ->assertSessionHasErrors([
            'password' => 'The password must be at least 8 characters and include at least one letter and one number.',
        ]);
});

test('registration rejects letter only passwords', function () {
    ensureCustomerRoleExists();

    $this->from(route('register'))
        ->post(route('register.store'), [
            'name' => 'Letters Only',
            'email' => 'letters-only@example.com',
            'password' => 'abcdefgh',
            'password_confirmation' => 'abcdefgh',
        ])
        ->assertSessionHasErrors([
            'password' => 'The password must be at least 8 characters and include at least one letter and one number.',
        ]);
});

test('registration still rejects password confirmation mismatches', function () {
    ensureCustomerRoleExists();

    $this->from(route('register'))
        ->post(route('register.store'), [
            'name' => 'Mismatch Check',
            'email' => 'mismatch@example.com',
            'password' => 'password1',
            'password_confirmation' => 'password2',
        ])
        ->assertSessionHasErrors([
            'password' => 'Your password confirmation does not match.',
        ]);
});

test('registration normalizes the stored email address', function () {
    ensureCustomerRoleExists();
    Notification::fake();

    $this->post(route('register.store'), [
        'name' => 'Normalized Email',
        'email' => 'Mixed.Case@Example.com',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
    ])->assertRedirect(route('verification.notice'));

    $this->assertDatabaseHas('users', [
        'email' => 'mixed.case@example.com',
    ]);
});

test('registered user passwords are hashed before persistence', function () {
    ensureCustomerRoleExists();
    Notification::fake();

    $this->post(route('register.store'), [
        'name' => 'Password Check',
        'email' => 'hash-check@example.com',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
    ])->assertRedirect(route('verification.notice'));

    $user = User::query()->where('email', 'hash-check@example.com')->firstOrFail();

    expect($user->password)->not->toBe('Password123');
    expect(Hash::check('Password123', $user->password))->toBeTrue();
});

test('registration keeps a walk in review claim destination when the new account email matches', function () {
    ensureCustomerRoleExists();
    Notification::fake();
    $claimUrl = registerClaimDestinationUrl('claim.register@example.com');

    $this->get(route('register', ['intended' => $claimUrl]))
        ->assertOk();

    $this->post(route('register.store'), [
        'name' => 'Claim Register Customer',
        'email' => 'claim.register@example.com',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
    ])
        ->assertRedirect($claimUrl)
        ->assertSessionHas('status', 'We sent a verification link to your email address.');

    $user = User::query()->where('email', 'claim.register@example.com')->firstOrFail();

    $this->assertAuthenticatedAs($user);
    Notification::assertSentTo($user, VerifyEmail::class);
});
