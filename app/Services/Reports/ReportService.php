<?php

namespace App\Services\Reports;

use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Inventory\InventoryItem;
use App\Models\Orders\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportService
{
    public function build(string $report, array $filters, ?int $perPage = null): array
    {
        return match ($report) {
            'inventory' => $this->inventoryReport($filters, $perPage),
            'walk_in_sales' => $this->walkInSalesReport($filters, $perPage),
            'product_performance' => $this->productPerformanceReport($filters, $perPage),
            default => $this->salesReport($filters, $perPage),
        };
    }

    private function salesReport(array $filters, ?int $perPage): array
    {
        $query = Order::query()
            ->with(['handledBy'])
            ->where('source', 'online');

        $this->applyOrderFilters($query, $filters);

        $rows = $this->mapRows(
            $this->resolveRows((clone $query)->latest('placed_at'), $perPage),
            fn (Order $order): array => [
                $order->order_number,
                optional($order->placed_at)->format('Y-m-d H:i'),
                $order->customer_name ?: 'Customer',
                strtoupper((string) $order->payment_method),
                ucfirst($order->status),
                number_format((float) $order->grand_total, 2),
            ],
        );

        return [
            'title' => 'Sales Report',
            'columns' => ['Order', 'Date', 'Customer', 'Payment', 'Status', 'Total'],
            'rows' => $rows,
            'totals' => [
                'orders' => (clone $query)->count(),
                'sales' => (float) (clone $query)->sum('grand_total'),
            ],
        ];
    }

    private function inventoryReport(array $filters, ?int $perPage): array
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

        $rows = $this->mapRows(
            $this->resolveRows((clone $query)->orderBy('quantity_on_hand')->orderBy('id'), $perPage),
            fn (InventoryItem $item): array => [
                $item->variant?->sku,
                $item->variant?->product?->name,
                $item->variant?->name,
                $item->quantity_on_hand,
                $item->available_quantity,
                $item->reorder_level,
            ],
        );

        return [
            'title' => 'Inventory Report',
            'columns' => ['SKU', 'Product', 'Variant', 'On Hand', 'Available', 'Reorder Level'],
            'rows' => $rows,
            'totals' => [
                'items' => (clone $query)->count(),
                'units' => (int) (clone $query)->sum('quantity_on_hand'),
            ],
        ];
    }

    private function walkInSalesReport(array $filters, ?int $perPage): array
    {
        $query = Order::query()
            ->with(['handledBy'])
            ->where('source', 'walk_in');

        $this->applyOrderFilters($query, $filters);

        $rows = $this->mapRows(
            $this->resolveRows((clone $query)->latest('placed_at'), $perPage),
            fn (Order $order): array => [
                $order->order_number,
                optional($order->placed_at)->format('Y-m-d H:i'),
                $order->customer_name ?: 'Walk-in Customer',
                $order->handledBy?->name ?? 'Admin',
                strtoupper((string) $order->payment_method),
                number_format((float) $order->grand_total, 2),
            ],
        );

        return [
            'title' => 'Walk-in Sales Report',
            'columns' => ['Receipt', 'Date', 'Customer', 'Cashier', 'Payment', 'Total'],
            'rows' => $rows,
            'totals' => [
                'sales' => (clone $query)->count(),
                'amount' => (float) (clone $query)->sum('grand_total'),
            ],
        ];
    }

    private function productPerformanceReport(array $filters, ?int $perPage): array
    {
        $query = Product::query()
            ->with('category')
            ->withCount([
                'variants',
                'variants as active_variants_count' => fn (Builder $builder) => $builder->where('status', 'active'),
                'orderItems as order_lines_count' => function (Builder $builder) use ($filters): void {
                    $this->applyOrderItemFilters($builder, $filters);
                },
            ])
            ->withSum([
                'orderItems as units_sold' => function (Builder $builder) use ($filters): void {
                    $this->applyOrderItemFilters($builder, $filters);
                },
            ], 'quantity')
            ->withSum([
                'orderItems as gross_revenue' => function (Builder $builder) use ($filters): void {
                    $this->applyOrderItemFilters($builder, $filters);
                },
            ], 'line_total');

        if ($categoryId = $filters['category_id'] ?? null) {
            $query->where('category_id', $categoryId);
        }

        $rows = $this->mapRows(
            $this->resolveRows((clone $query)->orderByDesc('units_sold')->orderBy('name'), $perPage),
            fn (Product $product): array => [
                $product->name,
                $product->category?->name ?? 'Uncategorized',
                (int) ($product->units_sold ?? 0),
                'PHP '.number_format((float) ($product->gross_revenue ?? 0), 2),
                $product->variants_count,
                $product->active_variants_count,
            ],
        );

        return [
            'title' => 'Product Performance Report',
            'columns' => ['Product', 'Category', 'Units Sold', 'Revenue', 'Variants', 'Active Variants'],
            'rows' => $rows,
            'totals' => [
                'products' => (clone $query)->count(),
                'units_sold' => (int) (clone $query)->get()->sum(fn (Product $product): int => (int) ($product->units_sold ?? 0)),
                'revenue' => (float) (clone $query)->get()->sum(fn (Product $product): float => (float) ($product->gross_revenue ?? 0)),
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

    private function applyOrderItemFilters(Builder $query, array $filters): void
    {
        $query->whereHas('order', function (Builder $builder) use ($filters): void {
            $this->applyOrderFilters($builder, $filters);
        });
    }

    private function resolveRows(Builder $query, ?int $perPage): Collection|LengthAwarePaginator
    {
        if ($perPage === null) {
            return $query->get();
        }

        return $query->paginate($perPage, ['*'], 'report_page')->withQueryString();
    }

    private function mapRows(
        Collection|LengthAwarePaginator $rows,
        callable $mapper,
    ): Collection|LengthAwarePaginator {
        if ($rows instanceof LengthAwarePaginator) {
            $rows->setCollection($rows->getCollection()->map($mapper));

            return $rows;
        }

        return $rows->map($mapper);
    }
}
