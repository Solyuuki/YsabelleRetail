<?php

namespace App\Http\Controllers\Storefront\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\Catalog\ProductBrowseRequest;
use App\Models\Catalog\Product;
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

    public function show(Request $request, Product $product, ProductReviewService $reviews): View
    {
        $product->load(['category', 'variants.inventoryItem']);
        $viewerReview = $reviews->viewerReview($request->user(), $product);

        return view('storefront.catalog.products.show', [
            'product' => $product,
            'productReviewSummary' => $reviews->reviewSummary($product),
            'productReviews' => $reviews->paginateVisibleReviews($product, (int) config('storefront.catalog.reviews_per_page', 5)),
            'reviewEligibility' => $reviews->eligibilityFor($request->user(), $product, $viewerReview),
            'viewerReview' => $viewerReview,
            'storefrontTrustMarks' => $this->storefrontTrustMarks(),
            'relatedProducts' => Product::query()
                ->with(['category', 'variants.inventoryItem'])
                ->active()
                ->where('id', '!=', $product->id)
                ->where('category_id', $product->category_id)
                ->limit(4)
                ->get(),
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
