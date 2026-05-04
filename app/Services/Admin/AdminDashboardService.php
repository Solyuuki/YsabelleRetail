<?php

namespace App\Services\Admin;

use App\Models\Catalog\Product;
use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\StockMovement;
use App\Models\Orders\Order;
use App\Support\Admin\InventoryMovementType;
use Illuminate\Support\Collection;

class AdminDashboardService
{
    public function __construct(
        private readonly AdminActivityFeedService $activityFeed,
    ) {}

    public function summary(): array
    {
        return [
            'metrics' => $this->liveMetrics(),
            'recent_orders' => $this->recentOrderCards('online', 5),
            'recent_walk_ins' => $this->recentOrderCards('walk_in', 4),
            'inventory_alerts' => $this->inventoryAlertCards(),
            'live_activity' => $this->activityFeed->latestActivity(),
            'sales_chart' => $this->salesChart(),
            'stock_movement_summary' => $this->stockMovementSummary(),
        ];
    }

    private function liveMetrics(): array
    {
        $completedOrders = Order::query()->where('status', 'completed');

        return [
            'total_sales' => (float) (clone $completedOrders)->sum('grand_total'),
            'online_sales' => (float) (clone $completedOrders)->where('source', 'online')->sum('grand_total'),
            'walk_in_sales' => (float) (clone $completedOrders)->where('source', 'walk_in')->sum('grand_total'),
            'total_orders' => Order::query()->count(),
            'total_products' => Product::query()->count(),
            'low_stock_items' => $this->lowStockItemsQuery()->count(),
            'out_of_stock_items' => InventoryItem::query()->where('quantity_on_hand', 0)->count(),
            'pending_orders' => Order::query()->where('status', 'pending')->count(),
            'completed_orders' => (clone $completedOrders)->count(),
        ];
    }

    private function recentOrderCards(string $source, int $limit): Collection
    {
        return Order::query()
            ->latest('placed_at')
            ->where('source', $source)
            ->limit($limit)
            ->get()
            ->map(fn (Order $order): array => $this->mapOrderCard($order));
    }

    private function inventoryAlertCards(int $limit = 4): Collection
    {
        return $this->lowStockItemsQuery()
            ->with('variant.product')
            ->orderBy('quantity_on_hand')
            ->limit($limit)
            ->get()
            ->map(function (InventoryItem $item): array {
                $productName = $item->variant?->product?->name ?? 'Inventory item';
                $variantName = $item->variant?->name ?: 'Default variant';
                $sku = $item->variant?->sku ?: 'SKU unavailable';
                $onHand = (int) $item->quantity_on_hand;
                $reorderLevel = (int) $item->reorder_level;

                return [
                    'title' => $productName,
                    'sku' => $sku,
                    'variant' => $variantName,
                    'on_hand' => $onHand,
                    'reorder_level' => $reorderLevel,
                    'tone' => $onHand <= 2 ? 'danger' : 'warning',
                    'status' => $onHand <= 2 ? 'Critical' : 'Low stock',
                    'note' => $onHand <= 2
                        ? 'Restock before the next sales push.'
                        : 'Monitor this variant before the next weekend spike.',
                ];
            });
    }

    private function salesChart(): Collection
    {
        $start = now()->subDays(6)->startOfDay();

        $totals = Order::query()
            ->selectRaw("DATE(placed_at) as order_date, SUM(grand_total) as total, SUM(CASE WHEN source = 'online' THEN grand_total ELSE 0 END) as online_total, SUM(CASE WHEN source = 'walk_in' THEN grand_total ELSE 0 END) as walk_in_total, COUNT(*) as orders_count")
            ->where('status', 'completed')
            ->whereNotNull('placed_at')
            ->where('placed_at', '>=', $start)
            ->groupBy('order_date')
            ->get()
            ->keyBy('order_date');

        return collect(range(0, 6))
            ->map(function (int $offset) use ($start, $totals): array {
                $date = $start->copy()->addDays($offset);
                $row = $totals->get($date->toDateString());

                return [
                    'date' => $date->toDateString(),
                    'label' => $date->format('M d'),
                    'total' => (float) ($row->total ?? 0),
                    'online_total' => (float) ($row->online_total ?? 0),
                    'walk_in_total' => (float) ($row->walk_in_total ?? 0),
                    'orders_count' => (int) ($row->orders_count ?? 0),
                ];
            });
    }

    private function stockMovementSummary(): Collection
    {
        $labels = $this->stockMovementCategories();
        $typeCase = "CASE
            WHEN type IN ('".InventoryMovementType::STOCK_IN."', '".InventoryMovementType::BATCH_IMPORT."') THEN '".InventoryMovementType::STOCK_IN."'
            WHEN type = '".InventoryMovementType::ONLINE_SALE."' THEN '".InventoryMovementType::ONLINE_SALE."'
            WHEN type = '".InventoryMovementType::WALK_IN_SALE."' THEN '".InventoryMovementType::WALK_IN_SALE."'
            ELSE '".InventoryMovementType::ADJUSTMENT."'
        END";

        $totals = StockMovement::query()
            ->selectRaw("{$typeCase} as dashboard_type, SUM(quantity_delta) as total_quantity, COUNT(*) as total_records")
            ->whereIn('type', [
                InventoryMovementType::STOCK_IN,
                InventoryMovementType::BATCH_IMPORT,
                InventoryMovementType::ONLINE_SALE,
                InventoryMovementType::WALK_IN_SALE,
                InventoryMovementType::ADJUSTMENT,
                InventoryMovementType::STOCK_OUT,
            ])
            ->groupBy('dashboard_type')
            ->get()
            ->keyBy('dashboard_type');

        return collect($labels)
            ->map(fn (string $label, string $type): array => [
                'type' => $type,
                'label' => $label,
                'total_quantity' => (int) ($totals[$type]->total_quantity ?? 0),
                'total_records' => (int) ($totals[$type]->total_records ?? 0),
                'caption' => $this->stockMovementCaption($type),
                'direction' => $this->movementDirection((int) ($totals[$type]->total_quantity ?? 0)),
                'icon' => $this->stockMovementIcon($type),
            ])
            ->values();
    }

    private function mapOrderCard(Order $order): array
    {
        $source = $order->source === 'walk_in' ? 'Walk-in' : 'Online';
        $status = (string) $order->status;

        return [
            'id' => $order->getKey(),
            'reference' => $order->order_number,
            'customer' => $order->customer_name ?: 'Registered customer',
            'amount' => (float) $order->grand_total,
            'status' => (string) str($status)->headline(),
            'status_tone' => $status === 'completed' ? 'success' : 'warning',
            'source' => $source,
            'source_tone' => $order->source === 'walk_in' ? 'warning' : 'neutral',
            'placed_at' => optional($order->placed_at)->format('M d, Y h:i A') ?: 'Awaiting timestamp',
            'note' => $order->source === 'walk_in'
                ? ($order->payment_method ?: 'Counter checkout')
                : ($order->customer_email ?: 'Storefront checkout'),
            'url' => route('admin.orders.show', $order),
        ];
    }

    private function stockMovementCategories(): array
    {
        return [
            InventoryMovementType::STOCK_IN => 'Stock In',
            InventoryMovementType::ONLINE_SALE => 'Online Sale',
            InventoryMovementType::WALK_IN_SALE => 'Walk-in Sale',
            InventoryMovementType::ADJUSTMENT => 'Adjustments',
        ];
    }

    private function stockMovementCaption(string $type): string
    {
        return match ($type) {
            InventoryMovementType::STOCK_IN => 'Combined inbound stock from manual entries and import batches.',
            InventoryMovementType::ONLINE_SALE => 'Units deducted through completed storefront orders.',
            InventoryMovementType::WALK_IN_SALE => 'Units deducted from cashier or counter transactions.',
            InventoryMovementType::ADJUSTMENT => 'Manual reconciliations, corrections, and stock-out adjustments.',
            default => 'Inventory activity summary.',
        };
    }

    private function stockMovementIcon(string $type): string
    {
        return match ($type) {
            InventoryMovementType::STOCK_IN => 'stock-in',
            InventoryMovementType::ONLINE_SALE => 'orders',
            InventoryMovementType::WALK_IN_SALE => 'pos',
            default => 'inventory',
        };
    }

    private function movementDirection(int $quantity): string
    {
        return match (true) {
            $quantity > 0 => 'incoming',
            $quantity < 0 => 'outgoing',
            default => 'neutral',
        };
    }

    private function lowStockItemsQuery()
    {
        return InventoryItem::query()
            ->whereColumn('quantity_on_hand', '<=', 'reorder_level')
            ->where('quantity_on_hand', '>', 0);
    }
}
