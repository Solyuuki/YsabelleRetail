<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Catalog\ProductVariant;
use App\Models\Inventory\StockMovement;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $status = $request->query('status', 'all');

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
            ->paginate(15)
            ->withQueryString();

        return view('admin.inventory.index', [
            'variants' => $variants,
            'recentMovements' => StockMovement::query()
                ->with(['variant.product', 'actor', 'order'])
                ->latest('occurred_at')
                ->limit(14)
                ->get(),
            'filters' => compact('search', 'status'),
        ]);
    }
}
