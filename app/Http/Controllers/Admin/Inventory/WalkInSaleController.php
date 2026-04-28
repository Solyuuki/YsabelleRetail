<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Inventory\WalkInSaleRequest;
use App\Models\Catalog\ProductVariant;
use App\Services\Admin\WalkInSaleService;
use App\Support\Storefront\ProductMediaResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WalkInSaleController extends Controller
{
    public function __construct(
        private readonly ProductMediaResolver $productMedia,
    ) {}

    public function create(Request $request): View
    {
        $oldLines = collect(json_decode((string) $request->old('lines_json', '[]'), true))
            ->filter(fn (mixed $line): bool => is_array($line) && isset($line['variant_id'], $line['quantity']))
            ->values();

        $variants = ProductVariant::query()
            ->with(['product.category', 'inventoryItem'])
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

                    return $this->variantPayload($variant, (int) $line['quantity']);
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
        $page = max((int) $request->query('page', 1), 1);

        $variants = ProductVariant::query()
            ->select('product_variants.*')
            ->with(['product.category', 'inventoryItem'])
            ->where('status', 'active')
            ->whereHas('product', fn ($query) => $query->where('status', 'active'))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder
                        ->where('sku', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhereHas('product', function ($productQuery) use ($search): void {
                            $productQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('style_code', 'like', "%{$search}%")
                                ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', "%{$search}%"));
                        });
                });
            })
            ->distinct('product_variants.id')
            ->orderByDesc('product_variants.product_id')
            ->orderBy('sku')
            ->paginate(
                perPage: 8,
                columns: ['*'],
                pageName: 'page',
                page: $page,
            );

        return response()->json([
            'data' => $variants->getCollection()->map(fn (ProductVariant $variant): array => $this->variantPayload($variant))->values(),
            'meta' => [
                'current_page' => $variants->currentPage(),
                'last_page' => $variants->lastPage(),
                'per_page' => $variants->perPage(),
                'total' => $variants->total(),
                'from' => $variants->firstItem(),
                'to' => $variants->lastItem(),
            ],
        ]);
    }

    private function variantPayload(ProductVariant $variant, int $quantity = 0): array
    {
        $product = $variant->product;
        $category = $product?->category;
        $optionValues = collect($variant->option_values ?? [])
            ->filter(fn (mixed $value): bool => filled($value))
            ->map(fn (mixed $value, string $key): string => ucfirst($key).' '.trim((string) $value))
            ->values();

        return [
            'id' => $variant->id,
            'product_id' => $product?->id,
            'sku' => $variant->sku,
            'name' => $product?->name ?? 'Unknown product',
            'variant_name' => $variant->name,
            'variant_label' => $optionValues->isNotEmpty()
                ? $optionValues->implode(' / ')
                : $variant->name,
            'category_name' => $category?->name ?? 'Uncategorized',
            'price' => (float) $variant->price,
            'available_quantity' => $variant->inventoryItem?->available_quantity ?? 0,
            'image_url' => $this->productMedia->imageUrlFor($product),
            'image_alt' => $this->productMedia->altTextFor($product, $product?->name),
            'quantity' => $quantity,
        ];
    }
}
