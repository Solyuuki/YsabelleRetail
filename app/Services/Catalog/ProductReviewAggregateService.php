<?php

namespace App\Services\Catalog;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductReview;
use Illuminate\Support\Collection;

class ProductReviewAggregateService
{
    public function syncForProduct(Product $product): Product
    {
        $snapshot = ProductReview::query()
            ->where('product_id', $product->id)
            ->where('is_visible', true)
            ->selectRaw('COUNT(*) as review_count, COALESCE(AVG(rating), 0) as rating_average')
            ->first();

        $product->forceFill([
            'review_count' => (int) ($snapshot?->review_count ?? 0),
            'rating_average' => round((float) ($snapshot?->rating_average ?? 0), 1),
        ])->saveQuietly();

        return $product->refresh();
    }

    public function syncForProductId(?int $productId): void
    {
        if (! $productId) {
            return;
        }

        $product = Product::query()->find($productId);

        if ($product) {
            $this->syncForProduct($product);
        }
    }

    public function breakdownFor(Product $product): Collection
    {
        $counts = ProductReview::query()
            ->where('product_id', $product->id)
            ->where('is_visible', true)
            ->selectRaw('rating, COUNT(*) as aggregate_count')
            ->groupBy('rating')
            ->pluck('aggregate_count', 'rating');

        $total = max((int) $product->review_count, 0);

        return collect(range(5, 1))
            ->map(function (int $rating) use ($counts, $total): array {
                $count = (int) ($counts[$rating] ?? 0);

                return [
                    'rating' => $rating,
                    'count' => $count,
                    'share' => $total > 0 ? ($count / $total) * 100 : 0,
                ];
            });
    }
}
