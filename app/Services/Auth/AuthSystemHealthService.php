<?php

namespace App\Services\Auth;

use App\Models\Access\Role;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthSystemHealthService
{
    public const ADMIN_EMAIL = 'admin@ysabelle.store';

    public const CUSTOMER_EMAIL = 'customer@ysabelle.store';

    private const ADMIN_ROLE = 'admin';

    private const SUPER_ADMIN_ROLE = 'super-admin';

    private const CUSTOMER_ROLE = 'customer';

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $requiredRoles = collect($this->roleBlueprints())
            ->mapWithKeys(fn (array $role, string $slug): array => [$slug => Role::query()->where('slug', $slug)->exists()])
            ->all();

        $adminAccounts = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('slug', $this->adminRoleSlugs()))
            ->orderBy('email')
            ->get(['id', 'email', 'status', 'has_local_password', 'email_verified_at']);

        $customerAccounts = User::query()
            ->whereHas('roles', fn ($query) => $query->where('slug', self::CUSTOMER_ROLE))
            ->orderBy('email')
            ->get(['id', 'email', 'status', 'has_local_password', 'email_verified_at']);

        $usersMissingRoles = User::query()
            ->doesntHave('roles')
            ->orderBy('email')
            ->get(['id', 'email', 'status', 'has_local_password', 'email_verified_at']);

        $orphanUsers = User::query()
            ->whereDoesntHave('roles', fn ($query) => $query->whereIn('slug', $this->recognizedRoleSlugs()))
            ->orderBy('email')
            ->get(['id', 'email', 'status', 'has_local_password', 'email_verified_at']);

        $socialOnlyUsers = User::query()
            ->where('has_local_password', false)
            ->whereHas('socialAccounts')
            ->orderBy('email')
            ->get(['id', 'email', 'status', 'has_local_password', 'email_verified_at']);

        $socialUsersMissingCustomerRole = User::query()
            ->whereHas('socialAccounts')
            ->whereDoesntHave('roles', fn ($query) => $query->where('slug', self::CUSTOMER_ROLE))
            ->whereDoesntHave('roles', fn ($query) => $query->whereIn('slug', $this->adminRoleSlugs()))
            ->orderBy('email')
            ->get(['id', 'email', 'status', 'has_local_password', 'email_verified_at']);

        $seededAdmin = User::query()
            ->where('email', self::ADMIN_EMAIL)
            ->first(['id', 'email', 'status', 'has_local_password', 'email_verified_at']);

        $seededCustomer = User::query()
            ->where('email', self::CUSTOMER_EMAIL)
            ->first(['id', 'email', 'status', 'has_local_password', 'email_verified_at']);

        $healthy = collect([
            $requiredRoles[self::ADMIN_ROLE] ?? false,
            $requiredRoles[self::CUSTOMER_ROLE] ?? false,
            $adminAccounts->isNotEmpty(),
            $usersMissingRoles->isEmpty(),
            $socialUsersMissingCustomerRole->isEmpty(),
            $orphanUsers->isEmpty(),
        ])->every(fn (bool $state): bool => $state);

        return [
            'healthy' => $healthy,
            'required_roles' => $requiredRoles,
            'roles_count' => Role::query()->count(),
            'users_count' => User::query()->count(),
            'admin_accounts' => [
                'count' => $adminAccounts->count(),
                'emails' => $adminAccounts->pluck('email')->all(),
                'records' => $adminAccounts->map(fn (User $user): array => $this->userSummary($user))->all(),
            ],
            'customer_accounts' => [
                'count' => $customerAccounts->count(),
                'emails' => $customerAccounts->pluck('email')->all(),
                'records' => $customerAccounts->map(fn (User $user): array => $this->userSummary($user))->all(),
            ],
            'users_missing_roles' => [
                'count' => $usersMissingRoles->count(),
                'emails' => $usersMissingRoles->pluck('email')->all(),
            ],
            'orphan_users' => [
                'count' => $orphanUsers->count(),
                'emails' => $orphanUsers->pluck('email')->all(),
            ],
            'social_only_users' => [
                'count' => $socialOnlyUsers->count(),
                'emails' => $socialOnlyUsers->pluck('email')->all(),
            ],
            'social_users_missing_customer_role' => [
                'count' => $socialUsersMissingCustomerRole->count(),
                'emails' => $socialUsersMissingCustomerRole->pluck('email')->all(),
            ],
            'seeded_accounts' => [
                'admin' => $seededAdmin ? $this->userSummary($seededAdmin) : null,
                'customer' => $seededCustomer ? $this->userSummary($seededCustomer) : null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function repair(bool $restoreLocalAccounts = true): array
    {
        return DB::transaction(function () use ($restoreLocalAccounts): array {
            $before = $this->snapshot();

            $actions = [
                'roles_created' => [],
                'roles_updated' => [],
                'accounts_created' => [],
                'accounts_updated' => [],
                'users_repaired' => [],
                'notes' => [],
            ];

            $roles = $this->ensureRequiredRoles($actions);

            if ($restoreLocalAccounts) {
                $this->ensureSupportAccount('admin', $roles[self::ADMIN_ROLE], $actions);
                $this->ensureSupportAccount('customer', $roles[self::CUSTOMER_ROLE], $actions);
            }

            $this->repairUserRoleAssignments($roles[self::CUSTOMER_ROLE], $actions);

            $after = $this->snapshot();

            return [
                'before' => $before,
                'after' => $after,
                'actions' => $actions,
            ];
        });
    }

    public function ensureCustomerRole(User $user): void
    {
        $customerRole = $this->ensureRequiredRoles()['customer'];

        $user->roles()->syncWithoutDetaching([$customerRole->id]);
    }

    public function reconcileUserRole(User $user): void
    {
        $roles = $this->ensureRequiredRoles();

        if ($user->hasRole(...$this->recognizedRoleSlugs())) {
            return;
        }

        if (Str::lower($user->email) === self::ADMIN_EMAIL) {
            $user->roles()->syncWithoutDetaching([$roles[self::ADMIN_ROLE]->id]);

            return;
        }

        $user->roles()->syncWithoutDetaching([$roles[self::CUSTOMER_ROLE]->id]);
    }

    /**
     * @param  array<string, mixed>  $actions
     * @return array<string, Role>
     */
    private function ensureRequiredRoles(array &$actions = []): array
    {
        $roles = [];

        foreach ($this->roleBlueprints() as $slug => $attributes) {
            $existingRole = Role::query()->where('slug', $slug)->first();
            $role = Role::query()->updateOrCreate(['slug' => $slug], $attributes);

            if (! $existingRole) {
                $actions['roles_created'][] = $slug;
            } elseif (collect($attributes)->contains(fn (mixed $value, string $key): bool => $existingRole->{$key} !== $value)) {
                $actions['roles_updated'][] = $slug;
            }

            $roles[$slug] = $role;
        }

        return $roles;
    }

    /**
     * @param  array<string, mixed>  $actions
     */
    private function ensureSupportAccount(string $type, Role $role, array &$actions): void
    {
        $definition = $this->supportAccountDefinition($type);
        $email = $definition['email'];
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $definition['name'],
                'password' => $this->fallbackPasswordFor($type),
                'has_local_password' => true,
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );
        $created = $user->wasRecentlyCreated;
        $touched = $created;

        if (! $created) {
            $updates = [];

            if ($user->name !== $definition['name']) {
                $updates['name'] = $definition['name'];
            }

            if (! $user->hasLocalPassword()) {
                $updates['has_local_password'] = true;
            }

            if (! $user->isActive()) {
                $updates['status'] = 'active';
            }

            if (! $user->email_verified_at instanceof Carbon) {
                $updates['email_verified_at'] = now();
            }

            if (blank($user->password)) {
                $updates['password'] = $this->fallbackPasswordFor($type);
                $actions['notes'][] = "Generated a fallback password for {$email}.";
            }

            if ($updates !== []) {
                $user->fill($updates)->save();
                $touched = true;
            }
        }

        if (! $user->roles()->whereKey($role->id)->exists()) {
            $user->roles()->syncWithoutDetaching([$role->id]);
            $touched = true;
        }

        if (($user->profile?->preferred_name) !== $definition['preferred_name']) {
            $user->profile()->updateOrCreate([], [
                'preferred_name' => $definition['preferred_name'],
            ]);
            $touched = true;
        }

        if ($created) {
            $actions['accounts_created'][] = $email;

            if (app()->environment('local')) {
                $actions['notes'][] = "Created {$email} using the local recovery password baseline.";
            } else {
                $actions['notes'][] = "Created {$email} with a generated fallback password.";
            }

            return;
        }

        if ($touched) {
            $actions['accounts_updated'][] = $email;
        }
    }

    /**
     * @param  array<string, mixed>  $actions
     */
    private function repairUserRoleAssignments(Role $customerRole, array &$actions): void
    {
        User::query()
            ->whereDoesntHave('roles', fn ($query) => $query->whereIn('slug', $this->recognizedRoleSlugs()))
            ->orderBy('id')
            ->get()
            ->each(function (User $user) use ($customerRole, &$actions): void {
                $assignedRole = Str::lower($user->email) === self::ADMIN_EMAIL
                    ? $this->ensureRequiredRoles()[self::ADMIN_ROLE]
                    : $customerRole;

                $user->roles()->syncWithoutDetaching([$assignedRole->id]);
                $actions['users_repaired'][] = $user->email;
            });

        User::query()
            ->whereHas('socialAccounts')
            ->whereDoesntHave('roles', fn ($query) => $query->where('slug', self::CUSTOMER_ROLE))
            ->whereDoesntHave('roles', fn ($query) => $query->whereIn('slug', $this->adminRoleSlugs()))
            ->orderBy('id')
            ->get()
            ->each(function (User $user) use ($customerRole, &$actions): void {
                $user->roles()->syncWithoutDetaching([$customerRole->id]);
                $actions['users_repaired'][] = $user->email;
            });
    }

    /**
     * @return array<string, array{name: string, slug: string, description: string, is_system: bool}>
     */
    private function roleBlueprints(): array
    {
        return [
            self::SUPER_ADMIN_ROLE => [
                'name' => 'Super Admin',
                'slug' => self::SUPER_ADMIN_ROLE,
                'description' => 'Full access to Ysabelle Store operations.',
                'is_system' => true,
            ],
            self::ADMIN_ROLE => [
                'name' => 'Admin',
                'slug' => self::ADMIN_ROLE,
                'description' => 'Operational access for catalog, inventory, and order workflows.',
                'is_system' => true,
            ],
            self::CUSTOMER_ROLE => [
                'name' => 'Customer',
                'slug' => self::CUSTOMER_ROLE,
                'description' => 'Customer-facing account role.',
                'is_system' => true,
            ],
        ];
    }

    /**
     * @return array{name: string, email: string, preferred_name: string}
     */
    private function supportAccountDefinition(string $type): array
    {
        return match ($type) {
            'admin' => [
                'name' => 'Admin',
                'email' => self::ADMIN_EMAIL,
                'preferred_name' => 'Admin',
            ],
            'customer' => [
                'name' => 'Ysabelle Customer',
                'email' => self::CUSTOMER_EMAIL,
                'preferred_name' => 'Ysabelle Customer',
            ],
            default => throw new \InvalidArgumentException("Unknown support account type [{$type}]."),
        };
    }

    private function fallbackPasswordFor(string $type): string
    {
        $configuredPassword = match ($type) {
            'admin' => env('AUTH_RECOVERY_ADMIN_PASSWORD'),
            'customer' => env('AUTH_RECOVERY_CUSTOMER_PASSWORD'),
            default => null,
        };

        if (filled($configuredPassword)) {
            return (string) $configuredPassword;
        }

        if (app()->environment('local')) {
            return 'Password123x';
        }

        return Str::password(24);
    }

    /**
     * @return list<string>
     */
    private function adminRoleSlugs(): array
    {
        return [self::ADMIN_ROLE, self::SUPER_ADMIN_ROLE];
    }

    /**
     * @return list<string>
     */
    private function recognizedRoleSlugs(): array
    {
        return [self::ADMIN_ROLE, self::SUPER_ADMIN_ROLE, self::CUSTOMER_ROLE];
    }

    /**
     * @return array<string, mixed>
     */
    private function userSummary(User $user): array
    {
        return [
            'email' => $user->email,
            'status' => $user->status,
            'has_local_password' => (bool) $user->has_local_password,
            'email_verified' => $user->email_verified_at !== null,
        ];
    }
}
