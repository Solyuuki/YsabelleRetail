<?php

namespace App\Services\Admin;

use App\Models\Catalog\ProductVariant;
use App\Models\Inventory\InventoryImportBatch;
use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\StockMovement;
use App\Support\Admin\InventoryMovementType;
use Illuminate\Http\Request;

class StockManagementService
{
    public function buildPageData(Request $request, array $overrides = []): array
    {
        $search = trim((string) $request->query('search'));
        $status = $request->query('status', 'all');
        $activeTab = $overrides['activeTab'] ?? $request->query('tab', 'inventory');
        $movementType = $overrides['movementType'] ?? $request->query('type', InventoryMovementType::STOCK_IN);
        $selectedVariantId = (int) ($overrides['selectedVariantId'] ?? $request->query('variant'));
        $preview = $overrides['preview'] ?? session('inventory_import_preview');

        if (! in_array($movementType, InventoryMovementType::manualTypes(), true)) {
            $movementType = InventoryMovementType::STOCK_IN;
        }

        $variants = ProductVariant::query()
            ->with(['product.category', 'inventoryItem'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder
                        ->where('sku', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhereHas('product', fn ($productQuery) => $productQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($status !== 'all', function ($query) use ($status): void {
                match ($status) {
                    'low' => $query->whereHas('inventoryItem', fn ($inventory) => $inventory->whereColumn('quantity_on_hand', '<=', 'reorder_level')->where('quantity_on_hand', '>', 0)),
                    'out' => $query->whereHas('inventoryItem', fn ($inventory) => $inventory->where('quantity_on_hand', '<=', 0)),
                    default => $query->where('status', $status),
                };
            })
            ->orderBy('sku')
            ->paginate(15, ['*'], 'inventory_page')
            ->withQueryString();

        $stockMovements = StockMovement::query()
            ->with(['variant.product', 'actor', 'order', 'importBatch'])
            ->latest('occurred_at')
            ->paginate(10, ['*'], 'movements_page')
            ->withQueryString();

        $importBatches = InventoryImportBatch::query()
            ->with(['uploadedBy'])
            ->withCount('stockMovements')
            ->latest()
            ->paginate(10, ['*'], 'imports_page')
            ->withQueryString();

        $activeTab = in_array($activeTab, ['inventory', 'add-stock', 'batch-import', 'movements'], true)
            ? $activeTab
            : 'inventory';

        return [
            'activeTab' => $activeTab,
            'filters' => compact('search', 'status'),
            'inventoryOverview' => [
                'active_variants' => ProductVariant::query()->where('status', 'active')->count(),
                'units_on_hand' => (int) InventoryItem::query()->sum('quantity_on_hand'),
                'low_stock_items' => InventoryItem::query()
                    ->whereColumn('quantity_on_hand', '<=', 'reorder_level')
                    ->where('quantity_on_hand', '>', 0)
                    ->count(),
                'out_of_stock_items' => InventoryItem::query()->where('quantity_on_hand', '<=', 0)->count(),
                'batch_imports' => InventoryImportBatch::query()->count(),
            ],
            'variants' => $variants,
            'stockMovements' => $stockMovements,
            'importBatches' => $importBatches,
            'preview' => $preview,
            'movementType' => $movementType,
            'movementTypes' => InventoryMovementType::manualTypes(),
            'variantOptions' => $activeTab === 'add-stock'
                ? ProductVariant::query()
                    ->with('product')
                    ->orderBy('sku')
                    ->get()
                : collect(),
            'selectedVariantId' => $selectedVariantId > 0 ? $selectedVariantId : null,
        ];
    }
}
