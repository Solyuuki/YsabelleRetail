<?php

namespace App\Services\Dashboard;

use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use App\Models\Inventory\InventoryItem;
use App\Models\Orders\Order;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DashboardMetricsService
{
    public function summary(): array
    {
        return [
            'database_ready' => $this->tableExists('products'),
            'categories_count' => $this->safeCount('categories', fn (): int => Category::query()->count()),
            'products_count' => $this->safeCount('products', fn (): int => Product::query()->count()),
            'variants_count' => $this->safeCount('product_variants', fn (): int => ProductVariant::query()->count()),
            'inventory_items_count' => $this->safeCount('inventory_items', fn (): int => InventoryItem::query()->count()),
            'orders_count' => $this->safeCount('orders', fn (): int => Order::query()->count()),
        ];
    }

    private function safeCount(string $table, callable $callback): int
    {
        if (! $this->tableExists($table)) {
            return 0;
        }

        try {
            return $callback();
        } catch (Throwable) {
            return 0;
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }
}
