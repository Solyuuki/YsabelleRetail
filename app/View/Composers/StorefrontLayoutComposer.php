<?php

namespace App\View\Composers;

use App\Services\Catalog\CatalogQueryService;
use App\Services\Storefront\CartService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

class StorefrontLayoutComposer
{
    public function __construct(
        private readonly CatalogQueryService $catalogQuery,
        private readonly CartService $cartService,
    ) {
    }

    public function compose(View $view): void
    {
        $categories = collect();

        if ($this->catalogQuery->catalogIsAvailable()) {
            $categories = $this->catalogQuery->navigationCategories();
        }

        $view->with([
            'storefrontNavigation' => config('storefront.navigation', []),
            'storefrontFooter' => config('storefront.footer', []),
            'storefrontTrustMarks' => config('storefront.trust_marks') ?: $this->defaultTrustMarks(),
            'storefrontCategories' => $categories,
            'storefrontCartCount' => $this->cartService->itemCount(),
        ]);
    }

    private function defaultTrustMarks(): array
    {
        return [
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
