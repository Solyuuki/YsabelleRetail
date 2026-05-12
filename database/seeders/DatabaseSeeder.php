<?php

namespace Database\Seeders;

use Database\Seeders\Auth\RolesAndUsersSeeder;
use Database\Seeders\Catalog\CatalogSeeder;
use Database\Seeders\Demo\DemoCommerceSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndUsersSeeder::class,
            CatalogSeeder::class,
            DemoCommerceSeeder::class,
        ]);
    }
}
