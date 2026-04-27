<?php

namespace App\Services\Admin;

use App\Models\Audit\AuditLog;
use Illuminate\Support\Collection;

class AdminActivityFeedService
{
    public function snapshot(?int $afterId = null, int $activityLimit = 6): array
    {
        $currentCursor = (int) (AuditLog::query()->max('id') ?? 0);

        return [
            'cursor' => $currentCursor,
            'mode' => 'polling_fallback',
            'notifications' => $afterId !== null
                ? $this->notificationsAfter($afterId)->values()->all()
                : [],
            'activities' => $this->latestActivity($activityLimit)->values()->all(),
        ];
    }

    public function latestActivity(int $limit = 6): Collection
    {
        return AuditLog::query()
            ->with('actor')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (AuditLog $log): array => $this->transform($log));
    }

    private function notificationsAfter(int $afterId): Collection
    {
        return AuditLog::query()
            ->with('actor')
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit(12)
            ->get()
            ->map(fn (AuditLog $log): array => $this->transform($log));
    }

    private function transform(AuditLog $log): array
    {
        $metadata = $log->metadata ?? [];

        return match ($log->event) {
            'commerce.online_order.placed' => [
                'id' => $log->id,
                'type' => 'success',
                'title' => 'New online order',
                'message' => sprintf(
                    '%s placed %s for PHP %s.',
                    $metadata['customer_name'] ?: 'A customer',
                    $metadata['order_number'] ?: 'an order',
                    number_format((float) ($metadata['grand_total'] ?? 0), 2),
                ),
                'timestamp' => optional($log->created_at)->format('M d, h:i A'),
            ],
            'commerce.walk_in_sale.completed' => [
                'id' => $log->id,
                'type' => 'success',
                'title' => 'New walk-in sale',
                'message' => sprintf(
                    '%s was completed for PHP %s.',
                    $metadata['order_number'] ?: 'A receipt',
                    number_format((float) ($metadata['grand_total'] ?? 0), 2),
                ),
                'timestamp' => optional($log->created_at)->format('M d, h:i A'),
            ],
            default => $this->inventoryPayload($log, $metadata),
        };
    }

    private function inventoryPayload(AuditLog $log, array $metadata): array
    {
        $stockStatus = $metadata['stock_status'] ?? 'healthy';
        $title = match ($stockStatus) {
            'out' => 'Out of stock alert',
            'low' => 'Low stock alert',
            default => 'Inventory updated',
        };

        $type = match ($stockStatus) {
            'out' => 'error',
            'low' => 'error',
            default => 'success',
        };

        $productName = $metadata['product_name'] ?? 'Inventory item';
        $sku = $metadata['sku'] ?? 'SKU';
        $currentQuantity = (int) ($metadata['current_quantity'] ?? 0);
        $delta = (int) ($metadata['quantity_delta'] ?? 0);
        $direction = $delta > 0 ? '+' : '';

        return [
            'id' => $log->id,
            'type' => $type,
            'title' => $title,
            'message' => sprintf(
                '%s (%s) is now at %d units after %s%d.',
                $productName,
                $sku,
                $currentQuantity,
                $direction,
                $delta,
            ),
            'timestamp' => optional($log->created_at)->format('M d, h:i A'),
        ];
    }
}
