<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalog\SaveProductRequest;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Services\Admin\ProductUpsertService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $status = $request->query('status');
        $categoryId = $request->integer('category_id');

        $products = Product::query()
            ->with(['category', 'variants.inventoryItem'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('style_code', 'like', "%{$search}%")
                        ->orWhereHas('variants', fn ($variantQuery) => $variantQuery->where('sku', 'like', "%{$search}%"));
                });
            })
            ->when($status && $status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.catalog.products.index', [
            'products' => $products,
            'categories' => Category::query()->orderBy('name')->get(),
            'filters' => [
                'search' => $search,
                'status' => $status ?: 'all',
                'category_id' => $categoryId ?: null,
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.catalog.products.create', [
            'product' => new Product([
                'status' => 'active',
                'track_inventory' => true,
            ]),
            'categories' => Category::query()->orderBy('name')->get(),
        ]);
    }

    public function store(SaveProductRequest $request, ProductUpsertService $products): RedirectResponse
    {
        $product = $products->store($request->validated(), $request->user());

        return redirect()
            ->route('admin.catalog.products.edit', $product)
            ->with('toast', [
                'type' => 'success',
                'title' => 'Product saved',
                'message' => "{$product->name} is now available in the admin catalog.",
            ]);
    }

    public function edit(Product $product): View
    {
        return view('admin.catalog.products.edit', [
            'product' => $product->load(['variants.inventoryItem']),
            'categories' => Category::query()->orderBy('name')->get(),
        ]);
    }

    public function update(
        SaveProductRequest $request,
        Product $product,
        ProductUpsertService $products,
    ): RedirectResponse {
        $product = $products->update($product, $request->validated(), $request->user());

        return redirect()
            ->route('admin.catalog.products.edit', $product)
            ->with('toast', [
                'type' => 'success',
                'title' => 'Product updated',
                'message' => "{$product->name} was updated successfully.",
            ]);
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->update(['status' => 'archived']);

        return redirect()
            ->route('admin.catalog.products.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Product archived',
                'message' => "{$product->name} was archived safely.",
            ]);
    }
}
