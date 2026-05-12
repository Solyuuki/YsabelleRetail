<?php

use App\Models\Access\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeUserWithRole(string $slug, array $attributes = []): User
{
    $role = Role::query()->firstOrCreate(
        ['slug' => $slug],
        [
            'name' => str($slug)->headline()->toString(),
            'description' => "{$slug} role",
            'is_system' => true,
        ],
    );

    $user = User::factory()->create($attributes);
    $user->roles()->attach($role);

    return $user;
}

test('inactive accounts cannot sign in even with correct credentials', function () {
    $user = makeUserWithRole('customer', [
        'email' => 'inactive@example.com',
        'password' => 'Password123x',
        'status' => 'inactive',
    ]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'Password123x',
    ])
        ->assertSessionHasErrors(['email']);

    $this->assertGuest();
});

test('login is throttled after repeated invalid attempts', function () {
    makeUserWithRole('customer', [
        'email' => 'customer@example.com',
        'password' => 'Password123x',
        'status' => 'active',
    ]);

    foreach (range(1, 5) as $attempt) {
        $this->from(route('login'))
            ->post(route('login.store'), [
                'email' => 'customer@example.com',
                'password' => 'wrong-password',
            ])
            ->assertSessionHasErrors(['email']);
    }

    $this->from(route('login'))
        ->post(route('login.store'), [
            'email' => 'customer@example.com',
            'password' => 'wrong-password',
        ])
        ->assertSessionHasErrors(['email']);
});

test('customer login redirects to the storefront account area by default', function () {
    $customer = makeUserWithRole('customer', [
        'email' => 'customer.login@example.com',
        'password' => 'Password123x',
    ]);

    $this->post(route('login.store'), [
        'email' => $customer->email,
        'password' => 'Password123x',
    ])->assertRedirect(route('storefront.account.index'));
});

test('customer login respects an intended walk in review claim destination', function () {
    $customer = makeUserWithRole('customer', [
        'email' => 'claim.login@example.com',
        'password' => 'Password123x',
    ]);
    $claimUrl = url('/account/review-claims/'.str_repeat('1', 64));

    $this->get(route('login', ['intended' => $claimUrl]))
        ->assertOk();

    $this->post(route('login.store'), [
        'email' => $customer->email,
        'password' => 'Password123x',
    ])->assertRedirect($claimUrl);
});

test('manual login does not enable remember me unless explicitly requested', function () {
    $customer = makeUserWithRole('customer', [
        'email' => 'remember.off@example.com',
        'password' => 'Password123x',
    ]);

    $this->post(route('login.store'), [
        'email' => 'Remember.Off@Example.com',
        'password' => 'Password123x',
    ])
        ->assertRedirect(route('storefront.account.index'))
        ->assertCookieMissing(Auth::guard('web')->getRecallerName());

    $this->assertAuthenticatedAs($customer);
});

test('manual login only enables remember me when explicitly requested', function () {
    $customer = makeUserWithRole('customer', [
        'email' => 'remember.on@example.com',
        'password' => 'Password123x',
    ]);

    $this->post(route('login.store'), [
        'email' => 'remember.on@example.com',
        'password' => 'Password123x',
        'remember' => '1',
    ])
        ->assertRedirect(route('storefront.account.index'))
        ->assertCookie(Auth::guard('web')->getRecallerName());

    $this->assertAuthenticatedAs($customer);
});

test('admin login is rejected from the customer login portal', function () {
    $admin = makeUserWithRole('admin', [
        'email' => 'admin.login@example.com',
        'password' => 'Password123x',
    ]);

    $this->post(route('login.store'), [
        'email' => $admin->email,
        'password' => 'Password123x',
    ])
        ->assertSessionHasErrors(['email']);

    $this->assertGuest();
});

test('admin accounts can sign in through the admin login portal', function () {
    $admin = makeUserWithRole('admin', [
        'email' => 'admin.portal@example.com',
        'password' => 'Password123x',
    ]);

    $this->get(route('login', ['portal' => 'admin']))
        ->assertOk()
        ->assertSeeText('Admin access mode is active for this sign-in session.');

    $this->post(route('login.store'), [
        'email' => $admin->email,
        'password' => 'Password123x',
    ])->assertRedirect(route('admin.dashboard'));
});

test('customer accounts cannot use the hidden admin portal to gain admin access', function () {
    $customer = makeUserWithRole('customer', [
        'email' => 'customer.portal@example.com',
        'password' => 'Password123x',
    ]);

    $this->get(route('login', ['portal' => 'admin']))
        ->assertOk()
        ->assertSeeText('Admin access mode is active for this sign-in session.');

    $this->post(route('login.store'), [
        'email' => $customer->email,
        'password' => 'Password123x',
    ])
        ->assertSessionHasErrors(['email']);

    $this->assertGuest();
});

test('social only accounts are rejected from manual password login with a dedicated message', function () {
    $customer = makeUserWithRole('customer', [
        'email' => 'social.login@example.com',
        'password' => 'Password123x',
        'has_local_password' => false,
    ]);

    $this->post(route('login.store'), [
        'email' => $customer->email,
        'password' => 'Password123x',
    ])
        ->assertSessionHasErrors([
            'email' => 'This account uses social sign-in. Continue with Google, Microsoft, or GitHub, or set a password first.',
        ]);

    $this->assertGuest();
});

test('portal specific throttle buckets do not poison the other portal', function () {
    $admin = makeUserWithRole('admin', [
        'email' => 'admin.throttle@example.com',
        'password' => 'Password123x',
    ]);

    foreach (range(1, 5) as $attempt) {
        $this->from(route('login'))
            ->post(route('login.store'), [
                'email' => $admin->email,
                'password' => 'wrong-password',
                'portal' => 'storefront',
            ])
            ->assertSessionHasErrors(['email']);
    }

    $this->post(route('login.store'), [
        'email' => $admin->email,
        'password' => 'Password123x',
        'portal' => 'admin',
    ])->assertRedirect(route('admin.dashboard'));
});

test('users without roles are auto repaired during a successful storefront login', function () {
    $user = User::factory()->create([
        'email' => 'roleless.login@example.com',
        'password' => 'Password123x',
        'has_local_password' => true,
        'status' => 'active',
    ]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'Password123x',
        'portal' => 'storefront',
    ])->assertRedirect(route('storefront.account.index'));

    expect($user->fresh()->hasRole('customer'))->toBeTrue();
});
