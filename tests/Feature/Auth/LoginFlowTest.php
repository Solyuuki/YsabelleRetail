<?php

use App\Models\Access\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeCustomer(array $attributes = []): User
{
    $role = Role::query()->create([
        'name' => 'Customer',
        'slug' => 'customer',
        'description' => 'Customer role',
        'is_system' => true,
    ]);

    $user = User::factory()->create($attributes);
    $user->roles()->attach($role);

    return $user;
}

test('inactive accounts cannot sign in even with correct credentials', function () {
    $user = makeCustomer([
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
    makeCustomer([
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
