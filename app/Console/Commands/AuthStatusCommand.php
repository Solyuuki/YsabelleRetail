<?php

namespace App\Console\Commands;

use App\Services\Auth\AuthSystemHealthService;
use Illuminate\Console\Command;

class AuthStatusCommand extends Command
{
    protected $signature = 'auth:status';

    protected $description = 'Display the current auth system health and recovery state.';

    public function handle(AuthSystemHealthService $health): int
    {
        $snapshot = $health->snapshot();

        $this->components->info('Auth system status');

        $this->table(
            ['Metric', 'Value'],
            [
                ['Healthy', $snapshot['healthy'] ? 'yes' : 'no'],
                ['Roles count', (string) $snapshot['roles_count']],
                ['Users count', (string) $snapshot['users_count']],
                ['Admin accounts', (string) $snapshot['admin_accounts']['count']],
                ['Customer accounts', (string) $snapshot['customer_accounts']['count']],
                ['Users missing roles', (string) $snapshot['users_missing_roles']['count']],
                ['Orphan users', (string) $snapshot['orphan_users']['count']],
                ['Social-only users', (string) $snapshot['social_only_users']['count']],
                ['Social users missing customer role', (string) $snapshot['social_users_missing_customer_role']['count']],
            ]
        );

        $this->table(
            ['Required role', 'Present'],
            collect($snapshot['required_roles'])
                ->map(fn (bool $present, string $slug): array => [$slug, $present ? 'yes' : 'no'])
                ->values()
                ->all()
        );

        $this->table(
            ['Portal config', 'Value'],
            [
                ['App URL', (string) config('app.url')],
                ['Admin portal URL', route('login', ['portal' => 'admin'])],
                ['Storefront portal URL', route('login')],
                ['Portal POST intent', 'explicit hidden input enabled'],
                ['Throttle isolation', 'email + ip + portal'],
            ]
        );

        $this->table(
            ['Bucket', 'Emails'],
            [
                ['Admin accounts', $this->formatList($snapshot['admin_accounts']['emails'])],
                ['Customer accounts', $this->formatList($snapshot['customer_accounts']['emails'])],
                ['Users missing roles', $this->formatList($snapshot['users_missing_roles']['emails'])],
                ['Orphan users', $this->formatList($snapshot['orphan_users']['emails'])],
                ['Social-only users', $this->formatList($snapshot['social_only_users']['emails'])],
                ['Social users missing customer role', $this->formatList($snapshot['social_users_missing_customer_role']['emails'])],
            ]
        );

        return $snapshot['healthy'] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  list<string>  $values
     */
    private function formatList(array $values): string
    {
        return $values === [] ? 'none' : implode(', ', $values);
    }
}
