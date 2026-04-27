<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Inventory\WalkInSaleRequest;
use App\Models\Catalog\ProductVariant;
use App\Services\Admin\WalkInSaleService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WalkInSaleController extends Controller
{
    public function create(Request $request): View
    {
        $oldLines = collect(json_decode((string) $request->old('lines_json', '[]'), true))
            ->filter(fn (mixed $line): bool => is_array($line) && isset($line['variant_id'], $line['quantity']))
            ->values();

        $variants = ProductVariant::query()
            ->with(['product', 'inventoryItem'])
            ->whereIn('id', $oldLines->pluck('variant_id'))
            ->get()
            ->keyBy('id');

        return view('admin.inventory.pos', [
            'oldLines' => $oldLines
                ->map(function (array $line) use ($variants): ?array {
                    $variant = $variants->get((int) $line['variant_id']);

                    if (! $variant) {
                        return null;
                    }

                    return [
                        'id' => $variant->id,
                        'sku' => $variant->sku,
                        'name' => $variant->product->name,
                        'variant_name' => $variant->name,
                        'price' => (float) $variant->price,
                        'available_quantity' => $variant->inventoryItem?->available_quantity ?? 0,
                        'quantity' => (int) $line['quantity'],
                    ];
                })
                ->filter()
                ->values(),
        ]);
    }

    public function store(WalkInSaleRequest $request, WalkInSaleService $sales): RedirectResponse
    {
        $order = $sales->create($request->validated(), $request->user());

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('toast', [
                'type' => 'success',
                'title' => 'Walk-in sale completed',
                'message' => "Receipt {$order->order_number} was created successfully.",
            ]);
    }

    public function search(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));

        $variants = ProductVariant::query()
            ->with(['product', 'inventoryItem'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder
                        ->where('sku', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhereHas('product', fn ($productQuery) => $productQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->where('status', 'active')
            ->limit(12)
            ->get()
            ->map(fn (ProductVariant $variant): array => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'name' => $variant->product->name,
                'variant_name' => $variant->name,
                'price' => (float) $variant->price,
                'available_quantity' => $variant->inventoryItem?->available_quantity ?? 0,
            ]);

        return response()->json([
            'data' => $variants,
        ]);
    }
}
