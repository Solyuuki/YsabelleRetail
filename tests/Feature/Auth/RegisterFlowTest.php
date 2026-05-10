<?php

use App\Models\Access\Role;
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
