<?php

namespace Database\Seeders\Auth;

use App\Services\Auth\AuthSystemHealthService;
use Illuminate\Database\Seeder;

class RolesAndUsersSeeder extends Seeder
{
    public function run(): void
    {
        app(AuthSystemHealthService::class)->repair();
    }
}
