<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Inventory\ManualStockMovementRequest;
use App\Models\Catalog\ProductVariant;
use App\Services\Admin\StockManagementService;
use App\Services\Inventory\InventoryManager;
use App\Support\Admin\InventoryMovementType;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ManualStockImportController extends Controller
{
    public function create(Request $request, StockManagementService $stock): View
    {
        return view('admin.inventory.index', $stock->buildPageData($request, [
            'activeTab' => 'add-stock',
            'movementType' => $request->query('type', InventoryMovementType::STOCK_IN),
            'selectedVariantId' => $request->query('variant'),
        ]));
    }

    public function store(
        ManualStockMovementRequest $request,
        InventoryManager $inventory,
    ): RedirectResponse {
        $variant = ProductVariant::query()->with(['product', 'inventoryItem'])->findOrFail($request->integer('product_variant_id'));
        $quantity = $request->integer('quantity');

        $inventory->recordManualChange(
            variant: $variant,
            quantity: $quantity,
            type: $request->string('type')->toString(),
            actor: $request->user(),
            referenceNumber: $request->string('reference_number')->toString() ?: null,
            notes: $request->string('notes')->toString() ?: null,
            unitCost: $request->filled('cost_price') ? (float) $request->input('cost_price') : null,
            supplierName: $request->string('supplier_name')->toString() ?: null,
        );

        return redirect()
            ->route('admin.inventory.index', ['tab' => 'movements'])
            ->with('toast', [
                'type' => 'success',
                'title' => 'Inventory updated',
                'message' => "Stock movement recorded for {$variant->product->name} ({$variant->sku}).",
            ]);
    }
}
