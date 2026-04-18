<?php

use App\Models\Access\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admins can access the admin dashboard', function () {
    $role = Role::query()->create([
        'name' => 'Admin',
        'slug' => 'admin',
        'description' => 'Admin role',
        'is_system' => true,
    ]);

    $user = User::factory()->create();
    $user->roles()->attach($role);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Operational Dashboard Foundation');
});
