<?php

namespace Database\Seeders\Users;

use App\Models\Access\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::query()->where('slug', 'admin')->first();
        $customerRole = Role::query()->where('slug', 'customer')->first();

        $this->backfillCustomerAccounts($customerRole);

        if (! app()->environment('local')) {
            return;
        }

        if ($adminRole && ! $this->hasAdminUser()) {
            $this->seedLocalAdminAccount($adminRole);
        }

        if ($customerRole) {
            $this->seedLocalCustomerAccount($customerRole);
        }
    }

    private function backfillCustomerAccounts(?Role $customerRole): void
    {
        if (! $customerRole) {
            return;
        }

        User::query()
            ->whereDoesntHave('roles', fn ($query) => $query->where('slug', 'customer'))
            ->whereDoesntHave('roles', fn ($query) => $query->whereIn('slug', ['admin', 'super-admin']))
            ->get()
            ->each(fn (User $user) => $user->roles()->syncWithoutDetaching([$customerRole->id]));
    }

    private function hasAdminUser(): bool
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('slug', ['admin', 'super-admin']))
            ->exists();
    }

    private function seedLocalAdminAccount(Role $adminRole): void
    {
        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@ysabelle.store'],
            [
                'name' => 'Admin',
                'password' => 'Password123x',
                'status' => 'active',
            ]
        );

        $admin->fill([
            'name' => 'Admin',
            'password' => 'Password123x',
            'status' => 'active',
        ])->save();

        $admin->roles()->syncWithoutDetaching([$adminRole->id]);
        $admin->profile()->updateOrCreate([], [
            'preferred_name' => 'Admin',
        ]);
    }

    private function seedLocalCustomerAccount(Role $customerRole): void
    {
        $customer = User::query()->firstOrCreate(
            ['email' => 'customer@ysabelle.store'],
            [
                'name' => 'Ysabelle Customer',
                'password' => 'Password123x',
                'status' => 'active',
            ]
        );

        $customer->roles()->syncWithoutDetaching([$customerRole->id]);
        $customer->profile()->updateOrCreate([], [
            'preferred_name' => 'Ysabelle Customer',
        ]);
    }
}
