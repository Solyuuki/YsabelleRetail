<?php

use App\Models\Access\Role;
use App\Models\Auth\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeRolelessSocialUser(array $attributes = []): User
{
    $user = User::factory()->create(array_merge([
        'email' => 'roleless-social@example.com',
        'has_local_password' => false,
        'status' => 'active',
    ], $attributes));

    SocialAccount::query()->create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_user_id' => 'google-'.$user->id,
        'provider_email' => $user->email,
    ]);

    return $user;
}

test('auth repair restores the required roles when the auth baseline is empty', function () {
    $this->artisan('auth:repair')->assertExitCode(0);

    expect(Role::query()->where('slug', 'admin')->count())->toBe(1)
        ->and(Role::query()->where('slug', 'customer')->count())->toBe(1);
});

test('auth repair recreates the baseline admin account when it is missing', function () {
    $this->artisan('auth:repair')->assertExitCode(0);

    User::query()->where('email', 'admin@ysabelle.store')->delete();

    $this->artisan('auth:repair')->assertExitCode(0);

    $admin = User::query()->where('email', 'admin@ysabelle.store')->first();

    expect($admin)->not->toBeNull()
        ->and($admin?->isActive())->toBeTrue()
        ->and($admin?->hasRole('admin'))->toBeTrue();
});

test('auth repair assigns the customer role to social users who are missing it', function () {
    $user = makeRolelessSocialUser();

    $this->artisan('auth:repair')->assertExitCode(0);

    expect($user->fresh()->hasRole('customer'))->toBeTrue();
});

test('auth repair is idempotent across repeated runs', function () {
    $this->artisan('auth:repair')->assertExitCode(0);
    $this->artisan('auth:repair')->assertExitCode(0);

    expect(Role::query()->where('slug', 'admin')->count())->toBe(1)
        ->and(Role::query()->where('slug', 'customer')->count())->toBe(1)
        ->and(User::query()->where('email', 'admin@ysabelle.store')->count())->toBe(1)
        ->and(User::query()->where('email', 'customer@ysabelle.store')->count())->toBe(1);
});

test('auth repair does not create duplicate required roles', function () {
    Role::query()->create([
        'name' => 'Admin',
        'slug' => 'admin',
        'description' => 'Admin role',
        'is_system' => true,
    ]);

    $this->artisan('auth:repair')->assertExitCode(0);

    expect(Role::query()->where('slug', 'admin')->count())->toBe(1)
        ->and(Role::query()->where('slug', 'customer')->count())->toBe(1);
});
