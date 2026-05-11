<?php

namespace App\Services\Catalog;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductReview;
use App\Models\Orders\OrderItem;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class ProductReviewService
{
    public function __construct(
        private readonly ProductReviewAggregateService $aggregates,
    ) {}

    public function paginateVisibleReviews(Product $product, int $perPage = 5): LengthAwarePaginator
    {
        return $product->reviews()
            ->where('is_visible', true)
            ->with('user')
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function reviewSummary(Product $product): array
    {
        $freshProduct = $product->fresh(['category', 'variants.inventoryItem']) ?? $product;

        return [
            'average' => $freshProduct->review_count > 0 ? (float) $freshProduct->rating_average : null,
            'count' => (int) $freshProduct->review_count,
            'breakdown' => $this->aggregates->breakdownFor($freshProduct),
        ];
    }

    public function viewerReview(?User $user, Product $product): ?ProductReview
    {
        if (! $user) {
            return null;
        }

        return ProductReview::query()
            ->where('product_id', $product->id)
            ->where('user_id', $user->id)
            ->first();
    }

    public function eligibilityFor(?User $user, Product $product, ?ProductReview $existingReview = null): array
    {
        if (! $user || ! $user->isCustomer()) {
            return [
                'can_review' => false,
                'reason' => 'Sign in with a customer account to leave a review.',
                'order_item' => null,
            ];
        }

        if ($existingReview) {
            return [
                'can_review' => true,
                'reason' => 'You can update or remove your review anytime.',
                'order_item' => $existingReview->orderItem,
            ];
        }

        $orderItem = $this->eligibleOrderItem($user, $product);

        if (! $orderItem) {
            return [
                'can_review' => false,
                'reason' => 'Only customers with a completed paid order for this product can review it.',
                'order_item' => null,
            ];
        }

        return [
            'can_review' => true,
            'reason' => 'Your review will be shown as a verified purchase.',
            'order_item' => $orderItem,
        ];
    }

    public function create(Product $product, User $user, array $payload): ProductReview
    {
        if ($this->viewerReview($user, $product)) {
            throw ValidationException::withMessages([
                'review' => 'You already have a review for this product.',
            ]);
        }

        $orderItem = $this->eligibleOrderItem($user, $product);

        if (! $orderItem) {
            throw ValidationException::withMessages([
                'review' => 'Only verified purchasers can leave a review for this product.',
            ]);
        }

        return ProductReview::query()->create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'order_item_id' => $orderItem->id,
            'rating' => (int) $payload['rating'],
            'title' => $payload['title'] ?: null,
            'body' => $payload['body'],
            'is_verified_purchase' => true,
            'is_visible' => true,
        ]);
    }

    public function update(ProductReview $review, User $user, array $payload): ProductReview
    {
        if ($review->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'review' => 'You can only update your own review.',
            ]);
        }

        $review->update([
            'rating' => (int) $payload['rating'],
            'title' => $payload['title'] ?: null,
            'body' => $payload['body'],
        ]);

        return $review->refresh();
    }

    public function delete(ProductReview $review, User $user): void
    {
        if ($review->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'review' => 'You can only delete your own review.',
            ]);
        }

        $review->delete();
    }

    private function eligibleOrderItem(User $user, Product $product): ?OrderItem
    {
        return OrderItem::query()
            ->select('order_items.*')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.product_id', $product->id)
            ->where('orders.user_id', $user->id)
            ->where('orders.status', 'completed')
            ->where('orders.payment_status', 'paid')
            ->orderByDesc('orders.placed_at')
            ->orderByDesc('order_items.id')
            ->with(['order', 'product'])
            ->first();
    }
}
