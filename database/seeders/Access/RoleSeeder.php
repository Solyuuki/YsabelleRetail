<?php

namespace Database\Seeders\Access;

use App\Models\Access\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            [
                'name' => 'Super Admin',
                'slug' => 'super-admin',
                'description' => 'Full access to Ysabelle Store operations.',
            ],
            [
                'name' => 'Admin',
                'slug' => 'admin',
                'description' => 'Operational access for catalog, inventory, and order workflows.',
            ],
            [
                'name' => 'Customer',
                'slug' => 'customer',
                'description' => 'Customer-facing account role.',
            ],
        ] as $role) {
            Role::query()->updateOrCreate(
                ['slug' => $role['slug']],
                $role + ['is_system' => true]
            );
        }
    }
}
