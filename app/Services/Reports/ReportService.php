<?php

namespace App\Services\Reports;

use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Inventory\InventoryItem;
use App\Models\Orders\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportService
{
    public function build(string $report, array $filters): array
    {
        return match ($report) {
            'inventory' => $this->inventoryReport($filters),
            'walk_in_sales' => $this->walkInSalesReport($filters),
            'product_performance' => $this->productPerformanceReport($filters),
            default => $this->salesReport($filters),
        };
    }

    private function salesReport(array $filters): array
    {
        $query = Order::query()
            ->with(['handledBy'])
            ->where('source', 'online');

        $this->applyOrderFilters($query, $filters);
        $orders = $query->latest('placed_at')->get();

        return [
            'title' => 'Sales Report',
            'columns' => ['Order', 'Date', 'Customer', 'Payment', 'Status', 'Total'],
            'rows' => $orders->map(fn (Order $order): array => [
                $order->order_number,
                optional($order->placed_at)->format('Y-m-d H:i'),
                $order->customer_name ?: 'Customer',
                strtoupper((string) $order->payment_method),
                ucfirst($order->status),
                number_format((float) $order->grand_total, 2),
            ]),
            'totals' => [
                'orders' => $orders->count(),
                'sales' => (float) $orders->sum('grand_total'),
            ],
        ];
    }

    private function inventoryReport(array $filters): array
    {
        $query = InventoryItem::query()->with('variant.product.category');

        if ($status = $filters['stock_status'] ?? null) {
            match ($status) {
                'low' => $query->whereColumn('quantity_on_hand', '<=', 'reorder_level')->where('quantity_on_hand', '>', 0),
                'out' => $query->where('quantity_on_hand', '<=', 0),
                default => null,
            };
        }

        if ($categoryId = $filters['category_id'] ?? null) {
            $query->whereHas('variant.product', fn (Builder $builder) => $builder->where('category_id', $categoryId));
        }

        $items = $query->orderBy('quantity_on_hand')->get();

        return [
            'title' => 'Inventory Report',
            'columns' => ['SKU', 'Product', 'Variant', 'On Hand', 'Available', 'Reorder Level'],
            'rows' => $items->map(fn (InventoryItem $item): array => [
                $item->variant?->sku,
                $item->variant?->product?->name,
                $item->variant?->name,
                $item->quantity_on_hand,
                $item->available_quantity,
                $item->reorder_level,
            ]),
            'totals' => [
                'items' => $items->count(),
                'units' => (int) $items->sum('quantity_on_hand'),
            ],
        ];
    }

    private function walkInSalesReport(array $filters): array
    {
        $query = Order::query()
            ->with(['handledBy'])
            ->where('source', 'walk_in');

        $this->applyOrderFilters($query, $filters);
        $orders = $query->latest('placed_at')->get();

        return [
            'title' => 'Walk-in Sales Report',
            'columns' => ['Receipt', 'Date', 'Customer', 'Cashier', 'Payment', 'Total'],
            'rows' => $orders->map(fn (Order $order): array => [
                $order->order_number,
                optional($order->placed_at)->format('Y-m-d H:i'),
                $order->customer_name,
                $order->handledBy?->name ?? 'Admin',
                strtoupper((string) $order->payment_method),
                number_format((float) $order->grand_total, 2),
            ]),
            'totals' => [
                'sales' => $orders->count(),
                'amount' => (float) $orders->sum('grand_total'),
            ],
        ];
    }

    private function productPerformanceReport(array $filters): array
    {
        $query = Product::query()
            ->with('category')
            ->withSum('variants as total_variant_price', 'price')
            ->withCount(['variants', 'variants as active_variants_count' => fn (Builder $builder) => $builder->where('status', 'active')]);

        if ($categoryId = $filters['category_id'] ?? null) {
            $query->where('category_id', $categoryId);
        }

        $products = $query->orderByDesc('review_count')->orderBy('name')->get();

        return [
            'title' => 'Product Performance Report',
            'columns' => ['Product', 'Category', 'Status', 'Variants', 'Reviews', 'Base Price'],
            'rows' => $products->map(fn (Product $product): array => [
                $product->name,
                $product->category?->name ?? 'Uncategorized',
                ucfirst($product->status),
                $product->variants_count,
                $product->review_count,
                number_format((float) $product->base_price, 2),
            ]),
            'totals' => [
                'products' => $products->count(),
                'active_products' => $products->where('status', 'active')->count(),
            ],
        ];
    }

    public function filterLookups(): array
    {
        return [
            'categories' => Category::query()->orderBy('name')->get(),
        ];
    }

    private function applyOrderFilters(Builder $query, array $filters): void
    {
        if ($dateFrom = $filters['date_from'] ?? null) {
            $query->whereDate('placed_at', '>=', $dateFrom);
        }

        if ($dateTo = $filters['date_to'] ?? null) {
            $query->whereDate('placed_at', '<=', $dateTo);
        }
    }
}
