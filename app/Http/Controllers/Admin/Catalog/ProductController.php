<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalog\SaveProductRequest;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Services\Admin\ProductCreationHealthService;
use App\Services\Admin\ProductLifecycleService;
use App\Services\Admin\ProductUpsertService;
use App\Services\Admin\ProductVisibilityDiagnosticsService;
use App\Services\Storefront\VisualSearchIndexService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToWriteFile;
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

    public function create(ProductCreationHealthService $health): View
    {
        return view('admin.catalog.products.create', [
            'product' => new Product([
                'status' => 'active',
                'force_new_badge' => false,
                'track_inventory' => true,
            ]),
            'categories' => Category::query()->orderBy('name')->get(),
            'productSystemHealth' => $health->snapshot(),
        ]);
    }

    public function store(
        SaveProductRequest $request,
        ProductUpsertService $products,
        ProductVisibilityDiagnosticsService $diagnostics,
        ProductCreationHealthService $health,
        VisualSearchIndexService $visualSearch,
    ): RedirectResponse
    {
        $healthSnapshot = $health->snapshot($request->hasFile('primary_image_upload'));

        if (! $healthSnapshot['ready']) {
            return back()
                ->withInput()
                ->with('toast', [
                    'type' => 'error',
                    'title' => 'Product create unavailable',
                    'message' => $healthSnapshot['blocking_message'],
                ]);
        }

        $payload = $request->validated();
        try {
            $product = $products->store($payload, $request->user());
        } catch (\Throwable $exception) {
            return $this->handleWriteFailure('create', $request, $exception);
        }

        $imageChanged = filled($product->primary_image_url);
        $visibility = $diagnostics->inspect($product);
        $imageSync = $imageChanged
            ? $this->syncVisualSearch($visualSearch, $product)
            : ['synced' => false, 'status' => 'not_requested'];

        return redirect()
            ->route('admin.catalog.products.edit', $product)
            ->with('toast', [
                'type' => 'success',
                'title' => 'Product saved',
                'message' => $this->buildSaveMessage($product, $imageChanged, $visibility, $imageSync, 'created'),
            ]);
    }

    public function edit(
        Product $product,
        ProductVisibilityDiagnosticsService $diagnostics,
        ProductLifecycleService $lifecycle,
        ProductCreationHealthService $health,
    ): View
    {
        $product = $product->load(['category', 'variants.inventoryItem']);

        return view('admin.catalog.products.edit', [
            'product' => $product,
            'categories' => Category::query()->orderBy('name')->get(),
            'visibilityDiagnostics' => $diagnostics->inspect($product),
            'deletionAssessment' => $lifecycle->deletionAssessment($product),
            'productSystemHealth' => $health->snapshot(),
        ]);
    }

    public function update(
        SaveProductRequest $request,
        Product $product,
        ProductUpsertService $products,
        ProductVisibilityDiagnosticsService $diagnostics,
        ProductCreationHealthService $health,
        VisualSearchIndexService $visualSearch,
    ): RedirectResponse {
        $healthSnapshot = $health->snapshot($request->hasFile('primary_image_upload'));

        if (! $healthSnapshot['ready']) {
            return back()
                ->withInput()
                ->with('toast', [
                    'type' => 'error',
                    'title' => 'Product update unavailable',
                    'message' => $healthSnapshot['blocking_message'],
                ]);
        }

        $previousImage = (string) ($product->primary_image_url ?? '');
        try {
            $product = $products->update($product, $request->validated(), $request->user());
        } catch (\Throwable $exception) {
            return $this->handleWriteFailure('update', $request, $exception);
        }

        $imageChanged = $previousImage !== (string) ($product->primary_image_url ?? '');
        $visibility = $diagnostics->inspect($product);
        $imageSync = $imageChanged
            ? $this->syncVisualSearch($visualSearch, $product)
            : ['synced' => false, 'status' => 'not_requested'];

        return redirect()
            ->route('admin.catalog.products.edit', $product)
            ->with('toast', [
                'type' => 'success',
                'title' => 'Product updated',
                'message' => $this->buildSaveMessage($product, $imageChanged, $visibility, $imageSync, 'updated'),
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

    public function restore(Product $product, ProductLifecycleService $lifecycle): RedirectResponse
    {
        $lifecycle->restore($product);

        return redirect()
            ->route('admin.catalog.products.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Product restored',
                'message' => "{$product->name} was restored as a draft.",
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

    private function buildSaveMessage(
        Product $product,
        bool $imageChanged,
        array $visibility,
        array $imageSync,
        string $operationPastTense,
    ): string
    {
        $messages = ['Product saved.'];

        if ($imageChanged && ! ($imageSync['synced'] ?? false)) {
            $messages[] = "Product {$operationPastTense}, but image search sync is pending or failed. Please check system health.";
        }

        if (! ($visibility['storefront_visible'] ?? false)) {
            $issue = $visibility['primary_issue'] ?? null;
            $messages[] = $issue
                ? 'Not yet visible on the storefront: '.$issue.'.'
                : 'Not yet visible on the storefront. Review the visibility checklist below.';
        }

        return implode(' ', $messages);
    }

    private function handleWriteFailure(string $operation, Request $request, \Throwable $exception): RedirectResponse
    {
        Log::error('admin.catalog.product_write_failed', [
            'operation' => $operation,
            'user_id' => $request->user()?->id,
            'product_name' => $request->input('name'),
            'product_slug' => $request->input('slug'),
            'exception_class' => $exception::class,
            'message' => $exception->getMessage(),
        ]);

        return back()
            ->withInput()
            ->with('toast', [
                'type' => 'error',
                'title' => $operation === 'create' ? 'Product not saved' : 'Product not updated',
                'message' => $this->friendlyWriteFailureMessage($exception),
            ]);
    }

    private function friendlyWriteFailureMessage(\Throwable $exception): string
    {
        $message = strtolower($exception->getMessage());

        if ($exception instanceof QueryException && str_contains($message, 'unknown column')) {
            return 'Product creation is temporarily unavailable because required catalog schema is missing. Please apply the latest catalog migrations and try again.';
        }

        if ($exception instanceof UnableToWriteFile || $exception instanceof UnableToCreateDirectory || str_contains($message, 'storage')) {
            return 'Product image storage is temporarily unavailable. Please restore public storage access and try again.';
        }

        return 'The product could not be saved because of a system error. No partial product data was committed.';
    }

    private function syncVisualSearch(VisualSearchIndexService $visualSearch, Product $product): array
    {
        try {
            return $visualSearch->syncProduct($product);
        } catch (\Throwable $exception) {
            Log::warning('admin.catalog.product_visual_sync_failed', [
                'product_id' => $product->id,
                'product_slug' => $product->slug,
                'exception_class' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return [
                'synced' => false,
                'status' => 'failed',
                'entries_indexed' => 0,
                'entries_deleted' => 0,
            ];
        }
    }
}
