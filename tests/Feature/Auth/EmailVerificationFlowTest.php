<?php

use App\Models\Access\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

function makeUnverifiedCustomer(array $attributes = []): User
{
    $role = Role::query()->firstOrCreate(
        ['slug' => 'customer'],
        [
            'name' => 'Customer',
            'description' => 'Customer role',
            'is_system' => true,
        ],
    );

    $user = User::factory()
        ->unverified()
        ->create(array_merge([
            'has_local_password' => true,
        ], $attributes));

    $user->roles()->syncWithoutDetaching([$role->id]);

    return $user;
}

test('verification notice page renders for authenticated unverified users', function () {
    $user = makeUnverifiedCustomer();

    $this->actingAs($user)
        ->get(route('verification.notice'))
        ->assertOk()
        ->assertSeeText('Verify your email')
        ->assertSeeText('Resend verification email');
});

test('verification notice redirects verified users back to their account', function () {
    $user = makeUnverifiedCustomer([
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('verification.notice'))
        ->assertRedirect(route('storefront.account.index'));
});

test('verification resend sends a fresh email link', function () {
    Notification::fake();
    $user = makeUnverifiedCustomer();

    $this->actingAs($user)
        ->post(route('verification.send'))
        ->assertRedirect();

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('verification resend requests are throttled', function () {
    Notification::fake();
    $user = makeUnverifiedCustomer();

    foreach (range(1, 6) as $attempt) {
        $this->actingAs($user)
            ->from(route('verification.notice'))
            ->post(route('verification.send'))
            ->assertRedirect(route('verification.notice'));
    }

    $this->actingAs($user)
        ->post(route('verification.send'))
        ->assertStatus(429);
});

test('valid email verification links mark the account as verified', function () {
    $user = makeUnverifiedCustomer([
        'email' => 'verify@example.com',
    ]);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        [
            'id' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
        ],
    );

    $this->actingAs($user)
        ->get($verificationUrl)
        ->assertRedirect(route('storefront.account.index'));

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});
