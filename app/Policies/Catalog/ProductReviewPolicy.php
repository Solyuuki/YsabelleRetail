<?php

namespace App\Policies\Catalog;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductReview;
use App\Models\User;

class ProductReviewPolicy
{
    public function create(User $user, Product $product): bool
    {
        return $user->isCustomer() && $product->status === 'active';
    }

    public function update(User $user, ProductReview $review): bool
    {
        return $user->isCustomer() && $review->user_id === $user->id;
    }

    public function delete(User $user, ProductReview $review): bool
    {
        return $user->isCustomer() && $review->user_id === $user->id;
    }
}
