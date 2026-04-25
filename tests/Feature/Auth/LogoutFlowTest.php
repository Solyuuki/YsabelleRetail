<?php

use App\Models\Access\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createUserWithRole(string $slug, array $attributes = []): User
{
    $role = Role::query()->create([
        'name' => str($slug)->headline()->toString(),
        'slug' => $slug,
        'description' => "{$slug} role",
        'is_system' => true,
    ]);

    $user = User::factory()->create($attributes);
    $user->roles()->attach($role);

    return $user;
}

test('customer logout posts safely and returns the user to the storefront as a guest', function () {
    $customer = createUserWithRole('customer');

    $this->actingAs($customer)
        ->post(route('logout'))
        ->assertRedirect(route('storefront.home'));

    $this->assertGuest();

    $this->get(route('storefront.home'))
        ->assertOk()
        ->assertSee('"isAuthenticated":false', escape: false)
        ->assertSee('"isAdmin":false', escape: false)
        ->assertSee('"isCustomer":false', escape: false);

    $this->get(route('storefront.account.index'))
        ->assertRedirect(route('login'));
});

test('admin logout posts safely and returns the user to the storefront as a guest', function () {
    $admin = createUserWithRole('admin');

    $this->actingAs($admin)
        ->post(route('logout'))
        ->assertRedirect(route('storefront.home'));

    $this->assertGuest();

    $this->get(route('storefront.home'))
        ->assertOk()
        ->assertSee('"isAuthenticated":false', escape: false)
        ->assertSee('"isAdmin":false', escape: false)
        ->assertSee('"isCustomer":false', escape: false);

    $this->get(route('admin.dashboard'))
        ->assertRedirect(route('login'));
});

test('direct get logout redirects home without attempting an insecure logout', function () {
    $customer = createUserWithRole('customer');

    $this->actingAs($customer)
        ->get('/logout')
        ->assertRedirect(route('storefront.home'));

    $this->assertAuthenticatedAs($customer);
});

test('authenticated customer pages render a csrf protected logout form', function () {
    $customer = createUserWithRole('customer');

    $this->actingAs($customer)
        ->get(route('storefront.account.index'))
        ->assertOk()
        ->assertSee('action="'.route('logout').'"', escape: false)
        ->assertSee('name="_token"', escape: false)
        ->assertSee('Sign out');
});

test('authenticated admin pages render a csrf protected logout form', function () {
    $admin = createUserWithRole('admin');

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('action="'.route('logout').'"', escape: false)
        ->assertSee('name="_token"', escape: false)
        ->assertSee('Sign out');
});
