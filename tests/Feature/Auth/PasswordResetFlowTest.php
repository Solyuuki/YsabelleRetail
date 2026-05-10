<?php

use App\Models\Access\Role;
use App\Models\Auth\SocialAccount;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

function makeManualPasswordUser(array $attributes = []): User
{
    $role = Role::query()->firstOrCreate(
        ['slug' => 'customer'],
        [
            'name' => 'Customer',
            'description' => 'Customer role',
            'is_system' => true,
        ],
    );

    $user = User::factory()->create(array_merge([
        'password' => 'Password123x',
        'has_local_password' => true,
    ], $attributes));

    $user->roles()->syncWithoutDetaching([$role->id]);

    return $user;
}

function makeSocialOnlyUser(array $attributes = []): User
{
    $user = makeManualPasswordUser(array_merge([
        'has_local_password' => false,
    ], $attributes));

    SocialAccount::query()->create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_user_id' => 'google-'.$user->id,
        'provider_email' => $user->email,
    ]);

    return $user;
}

test('forgot password always returns the generic recovery response', function () {
    Notification::fake();

    $user = makeManualPasswordUser([
        'email' => 'recover@example.com',
    ]);

    $this->from(route('password.request'))
        ->post(route('password.email'), [
            'email' => 'Recover@Example.com',
        ])
        ->assertRedirect(route('password.request'))
        ->assertSessionHas('status', 'If this email can receive reset instructions, we will send reset instructions shortly.');

    Notification::assertSentTo($user, ResetPassword::class);
});

test('forgot password does not expose whether the account exists', function () {
    Notification::fake();

    $this->from(route('password.request'))
        ->post(route('password.email'), [
            'email' => 'missing@example.com',
        ])
        ->assertRedirect(route('password.request'))
        ->assertSessionHas('status', 'If this email can receive reset instructions, we will send reset instructions shortly.');

    Notification::assertNothingSent();
});

test('social only accounts do not receive password reset links', function () {
    Notification::fake();

    $user = makeSocialOnlyUser([
        'email' => 'social-only@example.com',
    ]);

    $this->from(route('password.request'))
        ->post(route('password.email'), [
            'email' => $user->email,
        ])
        ->assertRedirect(route('password.request'))
        ->assertSessionHas('status', 'If this email can receive reset instructions, we will send reset instructions shortly.');

    Notification::assertNotSentTo($user, ResetPassword::class);
});

test('forgot password requests are throttled', function () {
    foreach (range(1, 6) as $attempt) {
        $this->from(route('password.request'))
            ->post(route('password.email'), [
                'email' => 'throttle@example.com',
            ])
            ->assertRedirect(route('password.request'));
    }

    $this->post(route('password.email'), [
        'email' => 'throttle@example.com',
    ])->assertStatus(429);
});

test('guest users can open reset password links and see the reset form', function () {
    $user = makeManualPasswordUser([
        'email' => 'guest-reset-link@example.com',
    ]);

    $token = Password::broker()->createToken($user);

    $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
        ->assertOk()
        ->assertSeeText('Choose a new password')
        ->assertSee('name="token" value="'.$token.'"', escape: false)
        ->assertSee('value="'.$user->email.'"', escape: false);
});

test('authenticated users can open reset password links without being redirected away', function () {
    $user = makeManualPasswordUser([
        'email' => 'authenticated-reset-link@example.com',
    ]);

    $token = Password::broker()->createToken($user);

    $this->actingAs($user)
        ->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
        ->assertOk()
        ->assertSeeText('Choose a new password')
        ->assertSee('name="token" value="'.$token.'"', escape: false)
        ->assertSee('value="'.$user->email.'"', escape: false);
});

test('valid reset password requests update the stored password', function () {
    $user = makeManualPasswordUser([
        'email' => 'resettable@example.com',
    ]);

    $token = Password::broker()->createToken($user);

    $this->post(route('password.store'), [
        'token' => $token,
        'email' => 'ResetTable@Example.com',
        'password' => 'password1',
        'password_confirmation' => 'password1',
    ])
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'Your password has been reset. You can now sign in.');

    $user->refresh();

    expect(Hash::check('password1', $user->password))->toBeTrue()
        ->and($user->hasLocalPassword())->toBeTrue();
});

test('authenticated users can complete password resets and are signed out safely afterward', function () {
    $user = makeManualPasswordUser([
        'email' => 'authenticated-reset-submit@example.com',
    ]);

    $token = Password::broker()->createToken($user);

    $this->actingAs($user)
        ->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'password1',
            'password_confirmation' => 'password1',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'Your password has been reset. You can now sign in.');

    $this->assertGuest();
    expect(Hash::check('password1', $user->fresh()->password))->toBeTrue();
});

test('reset password rejects passwords without a number', function () {
    $user = makeManualPasswordUser([
        'email' => 'reset-reject@example.com',
    ]);

    $token = Password::broker()->createToken($user);

    $this->from(route('password.reset', ['token' => $token, 'email' => $user->email]))
        ->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertRedirect(route('password.reset', ['token' => $token, 'email' => $user->email]))
        ->assertSessionHasErrors([
            'password' => 'The password must be at least 8 characters and include at least one letter and one number.',
        ]);
});

test('invalid or expired reset password tokens are rejected safely', function () {
    $user = makeManualPasswordUser([
        'email' => 'expired@example.com',
    ]);

    $this->from(route('password.reset', ['token' => 'invalid-token', 'email' => $user->email]))
        ->post(route('password.store'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])
        ->assertRedirect(route('password.reset', ['token' => 'invalid-token', 'email' => $user->email]))
        ->assertSessionHasErrors(['email']);
});

test('social only accounts cannot complete a password reset', function () {
    $user = makeSocialOnlyUser([
        'email' => 'blocked-social@example.com',
    ]);

    $token = Password::broker()->createToken($user);

    $this->from(route('password.reset', ['token' => $token, 'email' => $user->email]))
        ->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])
        ->assertRedirect(route('password.reset', ['token' => $token, 'email' => $user->email]))
        ->assertSessionHasErrors(['email']);
});
