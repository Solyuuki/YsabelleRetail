<?php

namespace App\Services\Admin;

use App\Models\Audit\AuditLog;
use App\Models\Inventory\StockMovement;
use App\Models\Orders\Order;

class AdminActivityLogger
{
    public function recordOrder(Order $order): void
    {
        AuditLog::query()->firstOrCreate(
            [
                'event' => $order->source === 'walk_in'
                    ? 'commerce.walk_in_sale.completed'
                    : 'commerce.online_order.placed',
                'subject_type' => $order->getMorphClass(),
                'subject_id' => $order->getKey(),
            ],
            [
                'actor_id' => $order->handled_by_user_id ?: $order->user_id,
                'metadata' => [
                    'order_number' => $order->order_number,
                    'source' => $order->source,
                    'customer_name' => $order->customer_name,
                    'grand_total' => (float) $order->grand_total,
                    'payment_method' => $order->payment_method,
                    'payment_status' => $order->payment_status,
                ],
            ],
        );
    }

    public function recordInventory(StockMovement $movement, int $currentQuantity): void
    {
        $movement->loadMissing(['inventoryItem', 'variant.product', 'order']);

        $reorderLevel = (int) ($movement->inventoryItem?->reorder_level ?? 0);
        $stockStatus = $currentQuantity <= 0
            ? 'out'
            : ($currentQuantity <= $reorderLevel ? 'low' : 'healthy');

        AuditLog::query()->firstOrCreate(
            [
                'event' => 'inventory.stock_changed',
                'subject_type' => $movement->getMorphClass(),
                'subject_id' => $movement->getKey(),
            ],
            [
                'actor_id' => $movement->actor_id,
                'metadata' => [
                    'movement_type' => $movement->type,
                    'quantity_delta' => (int) $movement->quantity_delta,
                    'reference_number' => $movement->reference_number,
                    'product_name' => $movement->variant?->product?->name,
                    'variant_name' => $movement->variant?->name,
                    'sku' => $movement->variant?->sku,
                    'current_quantity' => $currentQuantity,
                    'reorder_level' => $reorderLevel,
                    'stock_status' => $stockStatus,
                    'source' => $movement->order?->source
                        ?? ($movement->import_batch_id ? 'batch_import' : 'manual'),
                    'order_number' => $movement->order?->order_number,
                ],
            ],
        );
    }
}
