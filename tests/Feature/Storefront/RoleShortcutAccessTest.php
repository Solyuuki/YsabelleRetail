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
    $this->get(route('storefront.home'))
        ->assertOk()
        ->assertSee('id="ys-role-shortcuts-config"', escape: false)
        ->assertSee('"authenticated":false', escape: false);

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
        ->assertSee('"authenticated":true', escape: false)
        ->assertSee('"customer":true', escape: false)
        ->assertSee('"admin":false', escape: false);

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
        ->assertSee('"authenticated":true', escape: false)
        ->assertSee('"admin":true', escape: false)
        ->assertSee('"customer":false', escape: false);

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
