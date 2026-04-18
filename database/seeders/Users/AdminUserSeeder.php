<?php

namespace Database\Seeders\Users;

use App\Models\Access\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@ysabelle.store'],
            [
                'name' => 'Ysabelle Admin',
                'password' => 'password',
                'status' => 'active',
            ]
        );

        $customer = User::query()->updateOrCreate(
            ['email' => 'customer@ysabelle.store'],
            [
                'name' => 'Ysabelle Customer',
                'password' => 'password',
                'status' => 'active',
            ]
        );

        $adminRole = Role::query()->where('slug', 'admin')->first();
        $customerRole = Role::query()->where('slug', 'customer')->first();

        if ($adminRole) {
            $admin->roles()->syncWithoutDetaching([$adminRole->id]);
        }

        if ($customerRole) {
            $customer->roles()->syncWithoutDetaching([$customerRole->id]);
        }
    }
}
