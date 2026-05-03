<?php

use App\Models\Access\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

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

test('valid registration creates a real customer account', function () {
    ensureCustomerRoleExists();

    $this->from(route('register'))
        ->post(route('register.store'), [
            'name' => 'Ysabelle Shopper',
            'email' => 'shopper@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ])
        ->assertRedirect(route('storefront.account.index'));

    $user = User::query()->where('email', 'shopper@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user?->profile)->not->toBeNull();
    expect($user?->hasRole('customer'))->toBeTrue();

    $this->assertAuthenticatedAs($user);
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

test('registered user passwords are hashed before persistence', function () {
    ensureCustomerRoleExists();

    $this->post(route('register.store'), [
        'name' => 'Password Check',
        'email' => 'hash-check@example.com',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
    ])->assertRedirect(route('storefront.account.index'));

    $user = User::query()->where('email', 'hash-check@example.com')->firstOrFail();

    expect($user->password)->not->toBe('Password123');
    expect(Hash::check('Password123', $user->password))->toBeTrue();
});
