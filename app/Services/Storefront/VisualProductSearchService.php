<?php

namespace App\Services\Storefront;

use Illuminate\Http\UploadedFile;

class VisualProductSearchService
{
    public function __construct(
        private readonly ProductDiscoveryService $productDiscovery,
    ) {}

    public function search(UploadedFile $image, array $hints = []): array
    {
        $matchSet = $this->productDiscovery->findMatches([
            'brand_style' => $hints['brand_style'] ?? null,
            'color' => $hints['color'] ?? null,
            'category' => $hints['category'] ?? null,
            'use_case' => $hints['use_case'] ?? null,
            'filename' => pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME),
        ], 6);

        $products = $matchSet['products']
            ->map(fn ($product): array => $this->productDiscovery->formatProduct($product))
            ->values()
            ->all();

        return [
            'answer' => $this->answerFor($products, $matchSet['used_fallback']),
            'products' => $products,
            'actions' => [
                ['label' => 'Browse full catalog', 'type' => 'link', 'url' => route('storefront.shop')],
                ['label' => 'Ask assistant', 'type' => 'message', 'message' => 'Help me choose a shoe for daily use'],
            ],
        ];
    }

    private function answerFor(array $products, bool $usedFallback): string
    {
        if ($products === []) {
            return 'I could not find a strong visual match from the current catalog. Try adding a color, category, or use-case hint.';
        }

        if ($usedFallback) {
            return 'I did not find an exact metadata match, so these are the closest similar products from the current catalog.';
        }

        return 'These are the closest similar shoes I found based on your uploaded image hints.';
    }
}
