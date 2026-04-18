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
            'storefrontTrustMarks' => config('storefront.trust_marks', []),
            'storefrontCategories' => $categories,
            'storefrontCartCount' => $this->cartService->itemCount(),
        ]);
    }
}
