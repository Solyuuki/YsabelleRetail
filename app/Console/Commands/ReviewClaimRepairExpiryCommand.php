<?php

namespace App\Console\Commands;

use App\Services\Orders\ReviewClaimExpiryRepairService;
use Illuminate\Console\Command;

class ReviewClaimRepairExpiryCommand extends Command
{
    protected $signature = 'review-claims:repair-expiry';

    protected $description = 'Repair walk-in review claims affected by the expires_at auto-update schema bug.';

    public function handle(ReviewClaimExpiryRepairService $repair): int
    {
        $stats = $repair->repair();

        $this->components->info('Walk-in review claim expiry repair completed.');

        $this->table(
            ['Metric', 'Value'],
            [
                ['Affected', (string) $stats['affected']],
                ['Repaired', (string) $stats['repaired']],
                ['Skipped used', (string) $stats['skipped_used']],
                ['Skipped genuinely expired', (string) $stats['skipped_genuinely_expired']],
                ['Skipped invalid', (string) $stats['skipped_invalid']],
            ],
        );

        return self::SUCCESS;
    }
}
