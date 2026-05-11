<?php

namespace App\Observers\Catalog;

use App\Models\Catalog\ProductReview;
use App\Services\Catalog\ProductReviewAggregateService;

class ProductReviewObserver
{
    public function saved(ProductReview $review): void
    {
        $aggregates = app(ProductReviewAggregateService::class);

        $aggregates->syncForProductId($review->product_id);

        $originalProductId = $review->getOriginal('product_id');

        if ($originalProductId && $originalProductId !== $review->product_id) {
            $aggregates->syncForProductId($originalProductId);
        }
    }

    public function deleted(ProductReview $review): void
    {
        app(ProductReviewAggregateService::class)->syncForProductId($review->product_id);
    }
}
