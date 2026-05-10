<?php

namespace App\Services\Auth;

use App\Models\Access\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerAccountService
{
    public function register(string $name, string $email, string $password): User
    {
        return DB::transaction(function () use ($name, $email, $password): User {
            $user = User::query()->create([
                'name' => trim($name),
                'email' => Str::lower(trim($email)),
                'password' => $password,
                'has_local_password' => true,
                'status' => 'active',
            ]);

            $user->profile()->create([
                'preferred_name' => $user->name,
            ]);

            $this->ensureCustomerRole($user);

            return $user;
        });
    }

    public function registerFromSocial(
        string $name,
        string $email,
        bool $markEmailVerified = false,
    ): User {
        $user = DB::transaction(function () use ($name, $email): User {
            $user = User::query()->create([
                'name' => trim($name),
                'email' => Str::lower(trim($email)),
                'password' => Str::random(40),
                'has_local_password' => false,
                'status' => 'active',
            ]);

            $user->profile()->create([
                'preferred_name' => $user->name,
            ]);

            $this->ensureCustomerRole($user);

            return $user;
        });

        if ($markEmailVerified) {
            $user->forceFill([
                'email_verified_at' => now(),
            ])->save();
        }

        return $user;
    }

    public function ensureCustomerRole(User $user): void
    {
        $customerRole = Role::query()
            ->where('slug', 'customer')
            ->first();

        if (! $customerRole) {
            return;
        }

        $user->roles()->syncWithoutDetaching([$customerRole->id]);
    }
}
