<?php

namespace App\Services\Orders;

use App\Models\Orders\OrderReviewClaim;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;

class ReviewClaimExpiryRepairService
{
    /**
     * @return array{affected:int,repaired:int,skipped_used:int,skipped_genuinely_expired:int,skipped_invalid:int}
     */
    public function repair(): array
    {
        $counts = [
            'affected' => 0,
            'repaired' => 0,
            'skipped_used' => 0,
            'skipped_genuinely_expired' => 0,
            'skipped_invalid' => 0,
        ];

        OrderReviewClaim::query()
            ->with('order:id,user_id')
            ->whereNotNull('sent_at')
            ->whereColumn('expires_at', 'sent_at')
            ->lazyById()
            ->each(function (OrderReviewClaim $claim) use (&$counts): void {
                $counts['affected']++;

                if ($this->isUsed($claim)) {
                    $counts['skipped_used']++;

                    return;
                }

                $expectedExpiry = $this->expectedExpiryFor($claim);

                if (! $expectedExpiry) {
                    $counts['skipped_invalid']++;

                    return;
                }

                if ($expectedExpiry->lessThanOrEqualTo($this->storageNow())) {
                    $counts['skipped_genuinely_expired']++;

                    return;
                }

                $claim->forceFill([
                    'expires_at' => $expectedExpiry,
                ])->save();

                $counts['repaired']++;
            });

        return $counts;
    }

    private function expectedExpiryFor(OrderReviewClaim $claim): ?CarbonImmutable
    {
        if (! $claim->sent_at) {
            return null;
        }

        return CarbonImmutable::instance($claim->sent_at)
            ->setTimezone(BusinessTime::storageTimezone())
            ->addDays($this->claimLifetimeDays());
    }

    private function isUsed(OrderReviewClaim $claim): bool
    {
        return $claim->used_at !== null
            || $claim->claimed_by_user_id !== null
            || $claim->order?->user_id !== null;
    }

    private function storageNow(): CarbonImmutable
    {
        return CarbonImmutable::now(BusinessTime::storageTimezone());
    }

    private function claimLifetimeDays(): int
    {
        return max((int) config('storefront.review_claims.ttl_days', WalkInReviewClaimService::DEFAULT_CLAIM_TTL_DAYS), 1);
    }
}
