<?php

namespace App\Services\Admin;

use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\StockMovement;
use App\Models\Orders\Order;
use App\Models\Catalog\Product;
use App\Support\Admin\InventoryMovementType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AdminDashboardService
{
    public function summary(): array
    {
        return [
            'metrics' => [
                'total_sales' => (float) Order::query()->sum('grand_total'),
                'online_sales' => (float) Order::query()->where('source', 'online')->sum('grand_total'),
                'walk_in_sales' => (float) Order::query()->where('source', 'walk_in')->sum('grand_total'),
                'total_products' => Product::query()->count(),
                'low_stock_items' => $this->lowStockItemsQuery()->count(),
                'out_of_stock_items' => InventoryItem::query()->where('quantity_on_hand', '<=', 0)->count(),
                'pending_orders' => Order::query()->where('status', 'pending')->count(),
                'completed_orders' => Order::query()->where('status', 'completed')->count(),
            ],
            'recent_orders' => Order::query()
                ->with(['items', 'payments'])
                ->where('source', 'online')
                ->latest('placed_at')
                ->limit(6)
                ->get(),
            'recent_walk_in_sales' => Order::query()
                ->with(['items', 'payments', 'handledBy'])
                ->where('source', 'walk_in')
                ->latest('placed_at')
                ->limit(6)
                ->get(),
            'inventory_alerts' => $this->lowStockItemsQuery()
                ->with('variant.product')
                ->orderBy('quantity_on_hand')
                ->limit(8)
                ->get(),
            'sales_chart' => $this->salesChart(),
            'stock_movement_summary' => $this->stockMovementSummary(),
        ];
    }

    private function salesChart(): Collection
    {
        $start = now()->subDays(6)->startOfDay();

        $totals = Order::query()
            ->selectRaw('DATE(placed_at) as order_date, SUM(grand_total) as total')
            ->whereNotNull('placed_at')
            ->where('placed_at', '>=', $start)
            ->groupBy('order_date')
            ->pluck('total', 'order_date');

        return collect(range(0, 6))
            ->map(function (int $offset) use ($start, $totals): array {
                $date = $start->copy()->addDays($offset);

                return [
                    'date' => $date->toDateString(),
                    'label' => $date->format('M d'),
                    'total' => (float) ($totals[$date->toDateString()] ?? 0),
                ];
            });
    }

    private function stockMovementSummary(): Collection
    {
        $labels = InventoryMovementType::labels();

        $totals = StockMovement::query()
            ->selectRaw('type, SUM(quantity_delta) as total_quantity, COUNT(*) as total_records')
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        return collect($labels)
            ->map(fn (string $label, string $type): array => [
                'type' => $type,
                'label' => $label,
                'total_quantity' => (int) ($totals[$type]->total_quantity ?? 0),
                'total_records' => (int) ($totals[$type]->total_records ?? 0),
            ])
            ->values();
    }

    private function lowStockItemsQuery()
    {
        return InventoryItem::query()
            ->whereColumn('quantity_on_hand', '<=', 'reorder_level')
            ->where('quantity_on_hand', '>', 0);
    }
}
