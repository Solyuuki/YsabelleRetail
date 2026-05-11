<?php

use App\Models\Access\Role;
use App\Models\Cart\Cart;
use App\Models\Catalog\ProductVariant;
use App\Models\CheckoutDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createDraftRecoveryCustomer(array $attributes = []): User
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

function seedDraftRecoveryCart(User $user): Cart
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

function safeCheckoutDraftPayload(array $overrides = []): array
{
    return array_merge([
        'full_name' => 'Draft Customer',
        'email' => 'draft@example.com',
        'phone' => '09175551234',
        'city' => 'Pasig',
        'address' => '88 Emerald Avenue',
        'postal_code' => '1605',
        'order_notes' => 'Leave with concierge.',
        'payment_method' => 'card_simulated',
    ], $overrides);
}

test('checkout validation failure does not flash payment secrets or render them back', function () {
    $user = createDraftRecoveryCustomer();
    seedDraftRecoveryCart($user);

    $response = $this->from(route('storefront.checkout.create'))
        ->actingAs($user)
        ->post(route('storefront.checkout.store'), [
            ...safeCheckoutDraftPayload([
                'city' => '   ',
            ]),
            'cardholder_name' => 'Draft Holder',
            'card_number' => '5555444433331111',
            'card_expiry' => '09/99',
            'card_cvc' => '987',
        ]);

    $response->assertRedirect(route('storefront.checkout.create'))
        ->assertSessionHasErrors(['city'])
        ->assertSessionHas('_old_input', function (array $input): bool {
            return ! array_key_exists('card_number', $input)
                && ! array_key_exists('card_expiry', $input)
                && ! array_key_exists('card_cvc', $input)
                && ($input['payment_method'] ?? null) === 'card_simulated'
                && ($input['cardholder_name'] ?? null) === 'Draft Holder'
                && ($input['phone'] ?? null) === '09175551234';
        });

    $this->actingAs($user)
        ->get(route('storefront.checkout.create'))
        ->assertOk()
        ->assertDontSee('5555444433331111')
        ->assertDontSee('09/99')
        ->assertDontSee('987')
        ->assertSee('value="Draft Holder"', escape: false)
        ->assertSee('value="09175551234"', escape: false);
});

test('checkout draft saves only safe fields for authenticated customers', function () {
    $user = createDraftRecoveryCustomer();
    seedDraftRecoveryCart($user);

    $this->actingAs($user)
        ->putJson(route('storefront.checkout.draft.save'), [
            ...safeCheckoutDraftPayload(),
            'card_number' => '5555444433331111',
            'card_expiry' => '09/99',
            'card_cvc' => '987',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'saved');

    $draft = CheckoutDraft::query()->where('user_id', $user->id)->firstOrFail();

    expect($draft->payload)->toMatchArray(safeCheckoutDraftPayload())
        ->and($draft->payload)->not->toHaveKeys([
            'card_number',
            'card_expiry',
            'card_cvc',
        ]);
});

test('checkout draft restores safe fields after reload', function () {
    $user = createDraftRecoveryCustomer();
    seedDraftRecoveryCart($user);

    $this->actingAs($user)
        ->putJson(route('storefront.checkout.draft.save'), safeCheckoutDraftPayload([
            'full_name' => 'Recovered Customer',
            'email' => 'restored@example.com',
            'phone' => '09176667777',
            'city' => 'Taguig',
            'address' => '77 Market Street',
            'postal_code' => '1630',
            'order_notes' => 'Deliver after 5 PM.',
        ]))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('storefront.checkout.create'))
        ->assertOk()
        ->assertSee('value="Recovered Customer"', escape: false)
        ->assertSee('value="restored@example.com"', escape: false)
        ->assertSee('value="09176667777"', escape: false)
        ->assertSee('value="Taguig"', escape: false)
        ->assertSee('value="77 Market Street"', escape: false)
        ->assertSee('value="1630"', escape: false)
        ->assertSee('Deliver after 5 PM.');
});

test('successful checkout clears the active checkout draft', function () {
    $user = createDraftRecoveryCustomer();
    seedDraftRecoveryCart($user);

    $this->actingAs($user)
        ->putJson(route('storefront.checkout.draft.save'), safeCheckoutDraftPayload([
            'order_notes' => 'Draft-only order note',
        ]))
        ->assertOk();

    $this->actingAs($user)
        ->post(route('storefront.checkout.store'), safeCheckoutDraftPayload([
            'payment_method' => 'cod',
            'order_notes' => 'Submitted order note',
        ]))
        ->assertRedirect(route('storefront.account.index'));

    $this->assertDatabaseMissing('checkout_drafts', [
        'user_id' => $user->id,
    ]);

    seedDraftRecoveryCart($user);

    $this->actingAs($user)
        ->get(route('storefront.checkout.create'))
        ->assertOk()
        ->assertDontSee('Draft-only order note');
});

test('another user cannot access or reuse another customers checkout draft', function () {
    $firstUser = createDraftRecoveryCustomer([
        'name' => 'First Customer',
        'email' => 'first@example.com',
    ]);
    seedDraftRecoveryCart($firstUser);

    $secondUser = createDraftRecoveryCustomer([
        'name' => 'Second Customer',
        'email' => 'second@example.com',
    ]);
    seedDraftRecoveryCart($secondUser);

    $this->actingAs($firstUser)
        ->putJson(route('storefront.checkout.draft.save'), safeCheckoutDraftPayload([
            'city' => 'First Draft City',
            'address' => 'First Draft Address',
        ]))
        ->assertOk();

    $this->actingAs($secondUser)
        ->get(route('storefront.checkout.create'))
        ->assertOk()
        ->assertSee('value="Second Customer"', escape: false)
        ->assertSee('value="second@example.com"', escape: false)
        ->assertDontSee('First Draft City')
        ->assertDontSee('First Draft Address');

    $this->actingAs($secondUser)
        ->putJson(route('storefront.checkout.draft.save'), safeCheckoutDraftPayload([
            'city' => 'Second Draft City',
        ]))
        ->assertOk();

    expect(CheckoutDraft::query()->where('user_id', $firstUser->id)->firstOrFail()->payload['city'])->toBe('First Draft City')
        ->and(CheckoutDraft::query()->where('user_id', $secondUser->id)->firstOrFail()->payload['city'])->toBe('Second Draft City');
});

test('stale checkout drafts are ignored and cleaned up', function () {
    $user = createDraftRecoveryCustomer();
    seedDraftRecoveryCart($user);

    CheckoutDraft::query()->create([
        'user_id' => $user->id,
        'payload' => safeCheckoutDraftPayload([
            'city' => 'Expired Draft City',
            'address' => 'Expired Draft Address',
        ]),
        'expires_at' => now()->subDay(),
    ]);

    $this->actingAs($user)
        ->get(route('storefront.checkout.create'))
        ->assertOk()
        ->assertDontSee('Expired Draft City')
        ->assertDontSee('Expired Draft Address');

    $this->assertDatabaseMissing('checkout_drafts', [
        'user_id' => $user->id,
    ]);
});

test('guest users cannot access checkout draft endpoints', function () {
    $this->put(route('storefront.checkout.draft.save'), safeCheckoutDraftPayload())
        ->assertRedirect(route('login'));
});
