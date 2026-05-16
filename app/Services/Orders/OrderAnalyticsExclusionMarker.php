<?php

namespace App\Services\Orders;

use App\Models\Orders\Order;
use Illuminate\Support\Collection;

class OrderAnalyticsExclusionMarker
{
    public const REASON_REVIEW_SUPPORT = 'review_support_seed';

    public const REASON_DEMO_ONLINE = 'demo_seed_online';

    public const REASON_DEMO_WALK_IN = 'demo_seed_walk_in';

    private const REVIEW_SEED_NOTE = 'Demo verified-purchase order seeded for storefront review content.';

    private const ONLINE_DEMO_NOTE = 'Seeded online order for demo reporting.';

    private const WALK_IN_DEMO_NOTES = [
        'Walk-in sale from weekend foot traffic.',
        'Reserved item picked up in store.',
    ];

    public function mark(): array
    {
        $stats = [
            'scanned' => 0,
            'marked' => 0,
            'skipped' => 0,
            'already_excluded' => 0,
            'uncertain' => 0,
            'uncertain_orders' => [],
            'marked_by_reason' => [],
        ];

        Order::query()
            ->orderBy('id')
            ->get()
            ->each(function (Order $order) use (&$stats): void {
                $stats['scanned']++;

                $classification = $this->classify($order);

                if ($order->exclude_from_analytics) {
                    $stats['already_excluded']++;

                    return;
                }

                if ($classification['status'] === 'mark') {
                    $order->forceFill([
                        'exclude_from_analytics' => true,
                        'analytics_exclusion_reason' => $classification['reason'],
                    ])->save();

                    $stats['marked']++;
                    $stats['marked_by_reason'][$classification['reason']] = ($stats['marked_by_reason'][$classification['reason']] ?? 0) + 1;

                    return;
                }

                if ($classification['status'] === 'uncertain') {
                    $stats['uncertain']++;
                    $stats['uncertain_orders'][] = [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'source' => $order->source,
                        'reason' => $classification['note'],
                    ];

                    return;
                }

                $stats['skipped']++;
            });

        $stats['uncertain_orders'] = collect($stats['uncertain_orders'])
            ->take(10)
            ->values()
            ->all();

        ksort($stats['marked_by_reason']);

        return $stats;
    }

    private function classify(Order $order): array
    {
        if ($this->isSafeReviewSupportSeed($order)) {
            return [
                'status' => 'mark',
                'reason' => self::REASON_REVIEW_SUPPORT,
            ];
        }

        if ($this->isSafeOnlineDemoSeed($order)) {
            return [
                'status' => 'mark',
                'reason' => self::REASON_DEMO_ONLINE,
            ];
        }

        if ($this->isSafeWalkInDemoSeed($order)) {
            return [
                'status' => 'mark',
                'reason' => self::REASON_DEMO_WALK_IN,
            ];
        }

        $uncertainSignals = $this->uncertainSignals($order);

        if ($uncertainSignals->isNotEmpty()) {
            return [
                'status' => 'uncertain',
                'note' => $uncertainSignals->implode('; '),
            ];
        }

        return ['status' => 'skip'];
    }

    private function isSafeReviewSupportSeed(Order $order): bool
    {
        if ($order->source !== 'storefront') {
            return false;
        }

        $signals = [
            $this->hasReviewSeedMetadata($order),
            $this->matchesReviewSeedOrderNumber($order),
            $this->matchesReviewSeedNote($order),
            $this->hasDemoEmail($order),
        ];

        return $this->countTrueSignals($signals) >= 2;
    }

    private function isSafeOnlineDemoSeed(Order $order): bool
    {
        return $order->source === 'online'
            && $order->handled_by_user_id === null
            && $this->hasDemoEmail($order)
            && $this->matchesOnlineDemoNote($order);
    }

    private function isSafeWalkInDemoSeed(Order $order): bool
    {
        return $order->source === 'walk_in'
            && (bool) data_get($order->metadata, 'walk_in', false)
            && in_array($this->normalizedNote($order), self::WALK_IN_DEMO_NOTES, true);
    }

    private function uncertainSignals(Order $order): Collection
    {
        $signals = collect();

        if ($order->source === 'walk_in' && $order->handled_by_user_id === null && ! data_get($order->metadata, 'walk_in')) {
            $signals->push('walk-in order is missing cashier linkage and walk-in metadata');
        }

        if ($order->source === 'storefront' && ! $this->isSafeReviewSupportSeed($order)) {
            $signals->push('storefront order does not fully match the review-seed signature');
        }

        if ($order->source === 'online' && $this->hasDemoEmail($order) && ! $this->isSafeOnlineDemoSeed($order)) {
            $signals->push('online order uses a demo-domain email without the full seeded-online signature');
        }

        return $signals;
    }

    private function hasReviewSeedMetadata(Order $order): bool
    {
        return (bool) data_get($order->metadata, 'review_seed', false)
            || (bool) data_get($order->metadata, 'demo_seed', false);
    }

    private function matchesReviewSeedOrderNumber(Order $order): bool
    {
        return str_starts_with((string) $order->order_number, 'ORD-RVW-');
    }

    private function matchesReviewSeedNote(Order $order): bool
    {
        return $this->normalizedNote($order) === self::REVIEW_SEED_NOTE;
    }

    private function matchesOnlineDemoNote(Order $order): bool
    {
        return $this->normalizedNote($order) === self::ONLINE_DEMO_NOTE;
    }

    private function hasDemoEmail(Order $order): bool
    {
        return str_ends_with(strtolower((string) $order->customer_email), '@ysabelle.demo');
    }

    private function normalizedNote(Order $order): string
    {
        return trim((string) $order->notes);
    }

    private function countTrueSignals(array $signals): int
    {
        return count(array_filter($signals));
    }
}
