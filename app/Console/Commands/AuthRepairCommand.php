<?php

namespace App\Console\Commands;

use App\Services\Auth\AuthSystemHealthService;
use Illuminate\Console\Command;

class AuthRepairCommand extends Command
{
    protected $signature = 'auth:repair';

    protected $description = 'Repair missing roles, support accounts, and auth role assignments.';

    public function handle(AuthSystemHealthService $health): int
    {
        $result = $health->repair();
        $after = $result['after'];
        $actions = $result['actions'];

        $this->components->info('Auth repair completed.');

        $this->table(
            ['Metric', 'Value'],
            [
                ['Healthy after repair', $after['healthy'] ? 'yes' : 'no'],
                ['Roles count', (string) $after['roles_count']],
                ['Admin accounts', (string) $after['admin_accounts']['count']],
                ['Customer accounts', (string) $after['customer_accounts']['count']],
                ['Users missing roles', (string) $after['users_missing_roles']['count']],
                ['Orphan users', (string) $after['orphan_users']['count']],
                ['Social users missing customer role', (string) $after['social_users_missing_customer_role']['count']],
            ]
        );

        $this->table(
            ['Action', 'Details'],
            [
                ['Roles created', $this->formatList($actions['roles_created'])],
                ['Roles updated', $this->formatList($actions['roles_updated'])],
                ['Accounts created', $this->formatList($actions['accounts_created'])],
                ['Accounts updated', $this->formatList($actions['accounts_updated'])],
                ['Users repaired', $this->formatList(array_values(array_unique($actions['users_repaired'])))],
            ]
        );

        foreach ($actions['notes'] as $note) {
            $this->line(" - {$note}");
        }

        return $after['healthy'] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  list<string>  $values
     */
    private function formatList(array $values): string
    {
        return $values === [] ? 'none' : implode(', ', $values);
    }
}
