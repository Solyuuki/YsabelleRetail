<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalog\SaveProductRequest;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Services\Admin\ProductLifecycleService;
use App\Services\Admin\ProductUpsertService;
use App\Services\Admin\ProductVisibilityDiagnosticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use LogicException;

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
                'force_new_badge' => false,
                'track_inventory' => true,
            ]),
            'categories' => Category::query()->orderBy('name')->get(),
        ]);
    }

    public function store(
        SaveProductRequest $request,
        ProductUpsertService $products,
        ProductVisibilityDiagnosticsService $diagnostics,
    ): RedirectResponse
    {
        $payload = $request->validated();
        $product = $products->store($payload, $request->user());
        $imageChanged = filled($product->primary_image_url);
        $visibility = $diagnostics->inspect($product);

        return redirect()
            ->route('admin.catalog.products.edit', $product)
            ->with('toast', [
                'type' => 'success',
                'title' => 'Product saved',
                'message' => $this->buildSaveMessage($product, $imageChanged, $visibility),
            ]);
    }

    public function edit(
        Product $product,
        ProductVisibilityDiagnosticsService $diagnostics,
        ProductLifecycleService $lifecycle,
    ): View
    {
        $product = $product->load(['category', 'variants.inventoryItem']);

        return view('admin.catalog.products.edit', [
            'product' => $product,
            'categories' => Category::query()->orderBy('name')->get(),
            'visibilityDiagnostics' => $diagnostics->inspect($product),
            'deletionAssessment' => $lifecycle->deletionAssessment($product),
        ]);
    }

    public function update(
        SaveProductRequest $request,
        Product $product,
        ProductUpsertService $products,
        ProductVisibilityDiagnosticsService $diagnostics,
    ): RedirectResponse {
        $previousImage = (string) ($product->primary_image_url ?? '');
        $product = $products->update($product, $request->validated(), $request->user());
        $imageChanged = $previousImage !== (string) ($product->primary_image_url ?? '');
        $visibility = $diagnostics->inspect($product);

        return redirect()
            ->route('admin.catalog.products.edit', $product)
            ->with('toast', [
                'type' => 'success',
                'title' => 'Product updated',
                'message' => $this->buildSaveMessage($product, $imageChanged, $visibility),
            ]);
    }

    public function destroy(Product $product, ProductLifecycleService $lifecycle): RedirectResponse
    {
        $lifecycle->archive($product);

        return redirect()
            ->route('admin.catalog.products.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Product archived',
                'message' => "{$product->name} was archived safely.",
            ]);
    }

    public function purge(Product $product, ProductLifecycleService $lifecycle): RedirectResponse
    {
        $assessment = $lifecycle->deletionAssessment($product);

        if (! $assessment['can_delete']) {
            return redirect()
                ->route('admin.catalog.products.edit', $product)
                ->with('toast', [
                    'type' => 'warning',
                    'title' => 'Delete unavailable',
                    'message' => $assessment['message'],
                ]);
        }

        $productName = $product->name;

        try {
            $lifecycle->delete($product);
        } catch (LogicException) {
            return redirect()
                ->route('admin.catalog.products.edit', $product)
                ->with('toast', [
                    'type' => 'warning',
                    'title' => 'Delete unavailable',
                    'message' => $assessment['message'],
                ]);
        }

        return redirect()
            ->route('admin.catalog.products.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Product deleted',
                'message' => "{$productName} was deleted permanently.",
            ]);
    }

    private function buildSaveMessage(Product $product, bool $imageChanged, array $visibility): string
    {
        $messages = ['Product saved.'];

        if ($imageChanged) {
            $messages[] = 'Rebuild visual search index to include this image.';
        }

        if (! ($visibility['storefront_visible'] ?? false)) {
            $issue = $visibility['primary_issue'] ?? null;
            $messages[] = $issue
                ? 'Not yet visible on the storefront: '.$issue.'.'
                : 'Not yet visible on the storefront. Review the visibility checklist below.';
        }

        return implode(' ', $messages);
    }
}
