<?php

namespace Database\Seeders;

use Database\Seeders\Access\RoleSeeder;
use Database\Seeders\Catalog\CatalogSeeder;
use Database\Seeders\Users\AdminUserSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            AdminUserSeeder::class,
            CatalogSeeder::class,
        ]);
    }
}
