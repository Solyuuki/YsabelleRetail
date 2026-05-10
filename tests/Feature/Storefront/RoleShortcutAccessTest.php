<?php

use App\Models\Access\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seedShortcutRoles(): array
{
    $adminRole = Role::query()->create([
        'name' => 'Admin',
        'slug' => 'admin',
        'description' => 'Admin role',
        'is_system' => true,
    ]);

    $customerRole = Role::query()->create([
        'name' => 'Customer',
        'slug' => 'customer',
        'description' => 'Customer role',
        'is_system' => true,
    ]);

    return [$adminRole, $customerRole];
}

test('guest users are redirected away from protected admin and customer areas', function () {
    $expectedAdminAccess = route('login', ['portal' => 'admin']);

    $this->get(route('storefront.home'))
        ->assertOk()
        ->assertSee('window.AppAuth =', escape: false)
        ->assertSee('"isAuthenticated":false', escape: false)
        ->assertSee('"adminAccess":"'.$expectedAdminAccess.'"', escape: false)
        ->assertDontSee('"adminDashboard":"http', escape: false);

    $this->get(route('storefront.account.index'))
        ->assertRedirect(route('login'));

    $this->get(route('storefront.checkout.create'))
        ->assertRedirect(route('login'));

    $this->get(route('admin.dashboard'))
        ->assertRedirect(route('login'));
});

test('authenticated customers can access customer pages but not admin pages', function () {
    [, $customerRole] = seedShortcutRoles();

    $customer = User::factory()->create();
    $customer->roles()->attach($customerRole);

    $this->actingAs($customer)
        ->get(route('storefront.home'))
        ->assertOk()
        ->assertSee('"isAuthenticated":true', escape: false)
        ->assertSee('"isCustomer":true', escape: false)
        ->assertSee('"isAdmin":false', escape: false);

    $this->actingAs($customer)
        ->get(route('storefront.account.index'))
        ->assertOk();

    $this->actingAs($customer)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('admins can access admin pages but not customer-only pages without the customer role', function () {
    [$adminRole] = seedShortcutRoles();

    $admin = User::factory()->create();
    $admin->roles()->attach($adminRole);

    $this->actingAs($admin)
        ->get(route('storefront.home'))
        ->assertOk()
        ->assertSee('"isAuthenticated":true', escape: false)
        ->assertSee('"isAdmin":true', escape: false)
        ->assertSee('"isCustomer":false', escape: false)
        ->assertSeeText('Admin dashboard')
        ->assertDontSeeText('My account');

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('storefront.account.index'))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('storefront.checkout.create'))
        ->assertForbidden();
});

test('guest admin shortcut login intent redirects admins to the admin dashboard after login', function () {
    [$adminRole] = seedShortcutRoles();

    $admin = User::factory()->create([
        'email' => 'admin.shortcut@example.com',
        'password' => 'password',
    ]);
    $admin->roles()->attach($adminRole);

    $this->get(route('login', ['portal' => 'admin']))
        ->assertOk();

    $this->post(route('login.store'), [
        'email' => 'admin.shortcut@example.com',
        'password' => 'password',
    ])->assertRedirect(route('admin.dashboard'));
});

test('guest admin shortcut login intent does not grant admin access to non-admin users', function () {
    [, $customerRole] = seedShortcutRoles();

    $customer = User::factory()->create([
        'email' => 'customer.shortcut@example.com',
        'password' => 'password',
    ]);
    $customer->roles()->attach($customerRole);

    $this->get(route('login', ['portal' => 'admin']))
        ->assertOk();

    $this->post(route('login.store'), [
        'email' => 'customer.shortcut@example.com',
        'password' => 'password',
    ])
        ->assertRedirect(route('storefront.account.index'))
        ->assertSessionHas('toast', fn (array $toast) => ($toast['title'] ?? null) === 'Admin area unavailable');
});
