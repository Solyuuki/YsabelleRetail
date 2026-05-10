<?php

use App\Models\Access\Role;
use App\Models\User;
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

test('admin login redirects to the admin dashboard by default', function () {
    $admin = makeUserWithRole('admin', [
        'email' => 'admin.login@example.com',
        'password' => 'Password123x',
    ]);

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
        ->assertRedirect(route('storefront.account.index'))
        ->assertSessionHas('toast', fn (array $toast) => ($toast['title'] ?? null) === 'Admin area unavailable');
});
