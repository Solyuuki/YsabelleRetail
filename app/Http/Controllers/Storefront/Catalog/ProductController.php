<?php

namespace App\Http\Controllers\Storefront\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\Catalog\ProductBrowseRequest;
use App\Models\Catalog\Product;
use App\Services\Catalog\ProductAvailabilityService;
use App\Services\Catalog\CatalogQueryService;
use App\Services\Catalog\ProductReviewService;
use App\Support\Storefront\CatalogCollection;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(ProductBrowseRequest $request, CatalogQueryService $catalogQuery): View
    {
        $filters = $catalogQuery->resolveBrowseFilters($request->validated());
        $filterCategories = $catalogQuery->navigationCategories();
        $perPage = (int) ($filters['per_page'] ?? 12);
        $activeCollection = CatalogCollection::metadata($filters['collection'] ?? null);

        return view('storefront.catalog.products.index', [
            'products' => $catalogQuery->products($filters, $perPage),
            'filters' => $filters,
            'filterCategories' => $filterCategories,
            'activeCategory' => $filterCategories->firstWhere('slug', $filters['category'] ?? null),
            'activeCollection' => $activeCollection,
            'activeUseCaseLabel' => config('storefront.assistant.visual_search.use_cases.'.($filters['use_case'] ?? '')),
        ]);
    }

    public function show(
        Request $request,
        Product $product,
        ProductReviewService $reviews,
        ProductAvailabilityService $availability,
    ): View
    {
        $product->load(['category', 'variants.inventoryItem']);
        $viewerReview = $reviews->viewerReview($request->user(), $product);
        $relatedProducts = Product::query()
            ->with(['category', 'variants.inventoryItem'])
            ->active()
            ->where('id', '!=', $product->id)
            ->where('category_id', $product->category_id)
            ->get()
            ->filter(fn (Product $relatedProduct): bool => $availability->isDiscoverable($relatedProduct))
            ->sortBy(function (Product $relatedProduct) use ($availability): array {
                $relatedAvailability = $availability->forProduct($relatedProduct);

                return match ($relatedAvailability['state'] ?? null) {
                    ProductAvailabilityService::STATE_IN_STOCK => [0, $relatedProduct->name],
                    ProductAvailabilityService::STATE_LOW_STOCK => [1, $relatedProduct->name],
                    ProductAvailabilityService::STATE_BACKORDER_AVAILABLE => [2, $relatedProduct->name],
                    default => [3, $relatedProduct->name],
                };
            })
            ->take(4)
            ->values();

        return view('storefront.catalog.products.show', [
            'product' => $product,
            'productAvailability' => $availability->forProduct($product),
            'productReviewSummary' => $reviews->reviewSummary($product),
            'productReviews' => $reviews->paginateVisibleReviews($product, (int) config('storefront.catalog.reviews_per_page', 5)),
            'reviewEligibility' => $reviews->eligibilityFor($request->user(), $product, $viewerReview),
            'viewerReview' => $viewerReview,
            'storefrontTrustMarks' => $this->storefrontTrustMarks(),
            'relatedProducts' => $relatedProducts,
        ]);
    }

    private function storefrontTrustMarks(): array
    {
        return config('storefront.trust_marks') ?: [
            [
                'label' => 'Secure Checkout',
                'description' => 'Protected payments and safe transactions.',
            ],
            [
                'label' => 'Premium Quality',
                'description' => 'Carefully selected footwear for everyday performance.',
            ],
            [
                'label' => 'Fast Delivery',
                'description' => 'Reliable shipping for every confirmed order.',
            ],
        ];
    }
}
