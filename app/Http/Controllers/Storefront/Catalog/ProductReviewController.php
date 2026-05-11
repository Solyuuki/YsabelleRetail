<?php

namespace App\Http\Controllers\Storefront\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\Catalog\ProductReviewRequest;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductReview;
use App\Services\Catalog\ProductReviewService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;

class ProductReviewController extends Controller
{
    use AuthorizesRequests;

    public function store(ProductReviewRequest $request, Product $product, ProductReviewService $reviews): RedirectResponse
    {
        $this->authorize('create', [ProductReview::class, $product]);

        $reviews->create($product, $request->user(), $request->validated());

        return redirect()
            ->route('storefront.catalog.products.show', $product)
            ->with('success', 'Your review is now live.')
            ->withFragment('reviews');
    }

    public function update(ProductReviewRequest $request, Product $product, ProductReview $review, ProductReviewService $reviews): RedirectResponse
    {
        abort_unless($review->product_id === $product->id, 404);
        $this->authorize('update', $review);

        $reviews->update($review, $request->user(), $request->validated());

        return redirect()
            ->route('storefront.catalog.products.show', $product)
            ->with('success', 'Your review has been updated.')
            ->withFragment('reviews');
    }

    public function destroy(Product $product, ProductReview $review, ProductReviewService $reviews): RedirectResponse
    {
        abort_unless($review->product_id === $product->id, 404);
        $this->authorize('delete', $review);

        $reviews->delete($review, request()->user());

        return redirect()
            ->route('storefront.catalog.products.show', $product)
            ->with('success', 'Your review has been removed.')
            ->withFragment('reviews');
    }
}
