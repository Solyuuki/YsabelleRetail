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
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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
        $perPage = 8;

        $matchedVariants = $this->searchableVariants($search)->get();
        $matchedVariantIdsByGroup = $matchedVariants
            ->groupBy(fn (ProductVariant $variant): string => $this->groupingKey($variant))
            ->map(fn (Collection $variants): array => $variants->pluck('id')->all());

        $groups = $this->catalogGroupsFor($matchedVariants, $search)
            ->filter(fn (Collection $variants, string $groupKey): bool => $matchedVariantIdsByGroup->has($groupKey))
            ->map(fn (Collection $variants, string $groupKey): array => $this->groupPayload(
                variants: $variants,
                matchedVariantIds: $matchedVariantIdsByGroup->get($groupKey, []),
            ))
            ->values();

        $paginator = new LengthAwarePaginator(
            items: $groups->forPage($page, $perPage)->values(),
            total: $groups->count(),
            perPage: $perPage,
            currentPage: $page,
            options: [
                'path' => $request->url(),
                'pageName' => 'page',
            ],
        );

        return response()->json([
            'data' => $paginator->getCollection()->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    private function searchableVariants(string $search)
    {
        return ProductVariant::query()
            ->select('product_variants.*')
            ->with(['product.category', 'inventoryItem'])
            ->where('status', 'active')
            ->whereHas('product', fn ($query) => $query->where('status', 'active'))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder
                        ->where('sku', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('option_values->size', 'like', "%{$search}%")
                        ->orWhere('option_values->color', 'like', "%{$search}%")
                        ->orWhereHas('product', function ($productQuery) use ($search): void {
                            $productQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('style_code', 'like', "%{$search}%")
                                ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', "%{$search}%"));
                        });
                });
            })
            ->orderByDesc('product_variants.product_id')
            ->orderBy('sku');
    }

    private function catalogGroupsFor(Collection $matchedVariants, string $search): Collection
    {
        if ($matchedVariants->isEmpty()) {
            return collect();
        }

        $variants = $search === ''
            ? $matchedVariants
            : ProductVariant::query()
                ->with(['product.category', 'inventoryItem'])
                ->where('status', 'active')
                ->whereHas('product', fn ($query) => $query->where('status', 'active'))
                ->whereIn('product_id', $matchedVariants->pluck('product_id')->unique()->values())
                ->orderByDesc('product_id')
                ->orderBy('sku')
                ->get();

        return $variants
            ->groupBy(fn (ProductVariant $variant): string => $this->groupingKey($variant))
            ->map(fn (Collection $group): Collection => $group
                ->sort(fn (ProductVariant $first, ProductVariant $second): int => $this->compareVariants($first, $second))
                ->values()
            );
    }

    private function variantPayload(ProductVariant $variant, int $quantity = 0): array
    {
        $product = $variant->product;
        $category = $product?->category;
        $size = $this->optionValue($variant, 'size');
        $color = $this->optionValue($variant, 'color');
        $optionValues = $this->optionValuesForDisplay($variant);

        return [
            'id' => $variant->id,
            'product_id' => $product?->id,
            'sku' => $variant->sku,
            'name' => $product?->name ?? 'Unknown product',
            'variant_name' => $variant->name,
            'size' => $size,
            'color' => $color,
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

    private function groupPayload(Collection $variants, array $matchedVariantIds = []): array
    {
        /** @var ProductVariant $representative */
        $representative = $variants->first();
        $product = $representative->product;
        $prices = $variants->map(fn (ProductVariant $variant): float => (float) $variant->price);
        $minPrice = (float) $prices->min();
        $maxPrice = (float) $prices->max();
        $variantPayloads = $variants
            ->map(function (ProductVariant $variant) use ($matchedVariantIds): array {
                $payload = $this->variantPayload($variant);
                $payload['is_match'] = in_array($variant->id, $matchedVariantIds, true);

                return $payload;
            })
            ->values();

        return [
            'id' => $this->groupingKey($representative),
            'product_id' => $representative->product_id,
            'name' => $product?->name ?? 'Unknown product',
            'category_name' => $product?->category?->name ?? 'Uncategorized',
            'color' => $this->optionValue($representative, 'color') ?? 'Unspecified color',
            'image_url' => $this->productMedia->imageUrlFor($product),
            'image_alt' => $this->productMedia->altTextFor($product, $product?->name),
            'price' => $minPrice,
            'price_min' => $minPrice,
            'price_max' => $maxPrice,
            'has_price_range' => round($minPrice, 2) !== round($maxPrice, 2),
            'available_quantity' => $variants->sum(fn (ProductVariant $variant): int => $variant->inventoryItem?->available_quantity ?? 0),
            'variant_count' => $variants->count(),
            'matched_variant_ids' => array_values($matchedVariantIds),
            'badges' => array_values(array_filter([
                $product?->shows_sale_badge ? 'Sale' : null,
                $product?->shows_new_badge ? 'New' : null,
            ])),
            'variants' => $variantPayloads->all(),
        ];
    }

    private function groupingKey(ProductVariant $variant): string
    {
        return implode('::', [
            (string) $variant->product_id,
            Str::lower((string) ($this->optionValue($variant, 'color') ?? 'no-color')),
        ]);
    }

    private function optionValue(ProductVariant $variant, string $key): ?string
    {
        $value = data_get($variant->option_values, $key);

        if (! filled($value)) {
            return null;
        }

        return trim((string) $value);
    }

    private function optionValuesForDisplay(ProductVariant $variant): Collection
    {
        return collect($variant->option_values ?? [])
            ->filter(fn (mixed $value): bool => filled($value))
            ->map(fn (mixed $value, string $key): string => ucfirst($key).' '.trim((string) $value))
            ->values();
    }

    private function compareVariants(ProductVariant $first, ProductVariant $second): int
    {
        $sizeComparison = $this->compareSizeValues(
            $this->optionValue($first, 'size'),
            $this->optionValue($second, 'size'),
        );

        if ($sizeComparison !== 0) {
            return $sizeComparison;
        }

        return strcmp($first->sku, $second->sku);
    }

    private function compareSizeValues(?string $first, ?string $second): int
    {
        if ($first === $second) {
            return 0;
        }

        if (is_numeric($first) && is_numeric($second)) {
            return (float) $first <=> (float) $second;
        }

        return strcmp((string) $first, (string) $second);
    }
}
