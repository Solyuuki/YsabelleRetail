<?php

namespace App\Services\Storefront;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SmartShoppingAssistantService
{
    public function __construct(
        private readonly ProductDiscoveryService $productDiscovery,
        private readonly CartService $cartService,
        private readonly AuthFactory $auth,
    ) {}

    public function respond(string $message): array
    {
        $message = trim($message);
        $normalized = Str::lower($message);

        if ($this->isVisualSearchIntent($normalized)) {
            return $this->response(
                answer: 'Open Visual Search and upload a shoe photo. You can add color, category, or use-case hints to narrow the matches.',
                actions: [
                    ['label' => 'Open Visual Search', 'type' => 'panel', 'target' => 'visual-search'],
                    ['label' => 'Show sneakers', 'type' => 'message', 'message' => 'Show me sneakers'],
                ],
            );
        }

        if ($this->isCartIntent($normalized)) {
            return $this->cartResponse();
        }

        if ($topic = $this->policyTopic($normalized)) {
            return $this->policyResponse($topic);
        }

        if ($this->isCheckoutIntent($normalized)) {
            return $this->checkoutResponse();
        }

        if ($this->isLowStockIntent($normalized)) {
            return $this->lowStockResponse();
        }

        if ($this->isSizeIntent($normalized)) {
            return $this->sizeGuidanceResponse($message);
        }

        return $this->productResponse($message);
    }

    private function productResponse(string $message): array
    {
        $matchSet = $this->productDiscovery->findMatches(
            criteria: $this->productDiscovery->buildCriteriaFromText($message),
            limit: 4,
        );

        $products = $matchSet['products']->map(fn ($product): array => $this->productDiscovery->formatProduct($product))->all();
        $criteria = $matchSet['criteria'];

        if ($products === []) {
            return $this->response(
                answer: 'I could not find a strong product match from the current catalog yet. Try a category, color, budget, or size to narrow it down.',
                actions: $this->defaultActions(),
            );
        }

        $answer = match (true) {
            $criteria['max_price'] !== null && $matchSet['used_fallback'] => 'I could not find an exact match in that budget, but these are the closest options available right now.',
            $criteria['color'] && $criteria['category'] => 'Here are the best matches I found for that style and color.',
            $criteria['use_case'] === 'daily' => 'These are strong everyday options with versatile comfort and easy styling.',
            $criteria['use_case'] === 'running' || $criteria['category'] === 'running' => 'These are the best running-focused pairs I found in the current catalog.',
            str_contains(Str::lower($message), 'available') || str_contains(Str::lower($message), 'stock') => 'These are the closest available matches I found, with live stock status included.',
            default => 'These are the closest matches I found from the current catalog.',
        };

        return $this->response(
            answer: $answer,
            products: $products,
            actions: [
                ['label' => 'Open full catalog', 'type' => 'link', 'url' => route('storefront.shop')],
                ['label' => 'Check my cart', 'type' => 'message', 'message' => 'What is in my cart?'],
            ],
        );
    }

    private function sizeGuidanceResponse(string $message): array
    {
        $matchSet = $this->productDiscovery->findMatches(
            criteria: $this->productDiscovery->buildCriteriaFromText($message),
            limit: 3,
        );

        $products = $matchSet['products']->map(fn ($product): array => $this->productDiscovery->formatProduct($product))->all();
        $criteria = $matchSet['criteria'];

        $answer = match ($criteria['category'] ?? $criteria['use_case']) {
            'running' => 'For running shoes, start with your usual size and leave a little toe room for longer movement sessions.',
            'daily' => 'For daily-use pairs, your usual size is the safest starting point unless you prefer a roomier fit.',
            'gym' => 'For training pairs, keep the fit secure through the midfoot and avoid going too loose.',
            default => 'If you are between sizes, choose the size that gives you secure heel hold and a little room in front of the toes.',
        };

        if ($products !== []) {
            $answer .= ' I also pulled a few options that show the sizes currently listed in stock.';
        }

        return $this->response(
            answer: $answer,
            products: $products,
            actions: [
                ['label' => 'Find running shoes', 'type' => 'message', 'message' => 'Find running shoes'],
                ['label' => 'Check availability', 'type' => 'message', 'message' => 'What shoes are low stock?'],
            ],
        );
    }

    private function lowStockResponse(): array
    {
        $products = $this->productDiscovery->lowStockProducts(4)
            ->map(fn ($product): array => $this->productDiscovery->formatProduct($product))
            ->all();

        if ($products === []) {
            return $this->response(
                answer: 'Nothing is currently flagged as low stock in the active catalog. Most visible pairs still have comfortable inventory.',
                actions: [
                    ['label' => 'Browse all shoes', 'type' => 'link', 'url' => route('storefront.shop')],
                    ['label' => 'Black sneakers', 'type' => 'message', 'message' => 'Show me black sneakers'],
                ],
            );
        }

        return $this->response(
            answer: 'These pairs are the most time-sensitive right now based on current storefront inventory.',
            products: $products,
            actions: [
                ['label' => 'Open catalog', 'type' => 'link', 'url' => route('storefront.shop')],
                ['label' => 'Show my cart', 'type' => 'message', 'message' => 'What is in my cart?'],
            ],
        );
    }

    private function cartResponse(): array
    {
        $summary = $this->cartService->summary();

        if ($summary['is_empty']) {
            return $this->response(
                answer: 'Your cart is empty right now. I can help you find a running pair, a daily sneaker, or something close to a budget.',
                actions: [
                    ['label' => 'Find running shoes', 'type' => 'message', 'message' => 'Find running shoes'],
                    ['label' => 'Shoes under ₱3,000', 'type' => 'message', 'message' => 'Show me shoes under 3000'],
                    ['label' => 'Open catalog', 'type' => 'link', 'url' => route('storefront.shop')],
                ],
            );
        }

        $products = collect($summary['items'])
            ->map(fn ($item) => $item->variant?->product)
            ->filter()
            ->unique('id')
            ->take(4)
            ->map(fn ($product): array => $this->productDiscovery->formatProduct($product))
            ->values()
            ->all();

        $answer = 'Your cart has '.$summary['item_count'].' item'.($summary['item_count'] === 1 ? '' : 's')
            .' worth ₱'.number_format((float) $summary['total'], 0).'.';

        if ($summary['shipping'] > 0) {
            $answer .= ' Shipping is ₱'.number_format((float) $summary['shipping'], 0).' until you reach the free-shipping threshold.';
        } else {
            $answer .= ' You already qualify for free shipping.';
        }

        return $this->response(
            answer: $answer,
            products: $products,
            actions: [
                ['label' => 'View cart', 'type' => 'link', 'url' => route('storefront.cart.index')],
                [
                    'label' => 'Checkout',
                    'type' => 'link',
                    'url' => $this->auth->guard('web')->check() ? route('storefront.checkout.create') : route('login'),
                ],
            ],
        );
    }

    private function checkoutResponse(): array
    {
        $answer = 'Checkout uses your cart summary, shipping details, and either Cash on Delivery or the simulated card flow. ';
        $answer .= $this->auth->guard('web')->check()
            ? 'If your cart is ready, you can move straight to checkout.'
            : 'You will need to sign in with a customer account before placing an order.';

        return $this->response(
            answer: $answer,
            actions: [
                ['label' => 'View cart', 'type' => 'link', 'url' => route('storefront.cart.index')],
                [
                    'label' => $this->auth->guard('web')->check() ? 'Go to checkout' : 'Sign in to checkout',
                    'type' => 'link',
                    'url' => $this->auth->guard('web')->check() ? route('storefront.checkout.create') : route('login'),
                ],
            ],
        );
    }

    private function policyResponse(string $topic): array
    {
        $policies = config('storefront.assistant.policies', []);

        $answer = Arr::get($policies, $topic, 'I can help with shipping, returns, authenticity, or general store guidance.');

        if ($topic === 'shipping') {
            $answer .= ' The checkout summary always shows the exact shipping charge before you place the order.';
        }

        return $this->response(
            answer: $answer,
            actions: [
                ['label' => 'Checkout help', 'type' => 'message', 'message' => 'How do I checkout?'],
                ['label' => 'Check my cart', 'type' => 'message', 'message' => 'What is in my cart?'],
            ],
        );
    }

    private function response(string $answer, array $products = [], array $actions = []): array
    {
        return [
            'answer' => $answer,
            'products' => $products,
            'actions' => $actions === [] ? $this->defaultActions() : $actions,
        ];
    }

    private function defaultActions(): array
    {
        return [
            ['label' => 'Find running shoes', 'type' => 'message', 'message' => 'Find running shoes'],
            ['label' => 'Check my cart', 'type' => 'message', 'message' => 'What is in my cart?'],
            ['label' => 'Find similar by image', 'type' => 'panel', 'target' => 'visual-search'],
        ];
    }

    private function isVisualSearchIntent(string $message): bool
    {
        return str_contains($message, 'image')
            || str_contains($message, 'photo')
            || str_contains($message, 'picture')
            || str_contains($message, 'visual search')
            || str_contains($message, 'find similar');
    }

    private function isCartIntent(string $message): bool
    {
        return str_contains($message, 'cart')
            || str_contains($message, 'basket')
            || str_contains($message, 'bag');
    }

    private function isCheckoutIntent(string $message): bool
    {
        return str_contains($message, 'checkout')
            || str_contains($message, 'payment')
            || str_contains($message, 'place order')
            || str_contains($message, 'buy');
    }

    private function isLowStockIntent(string $message): bool
    {
        return str_contains($message, 'low stock')
            || str_contains($message, 'sold out')
            || str_contains($message, 'almost sold out')
            || str_contains($message, 'availability alert');
    }

    private function isSizeIntent(string $message): bool
    {
        return str_contains($message, 'size')
            || str_contains($message, 'fit')
            || str_contains($message, 'true to size');
    }

    private function policyTopic(string $message): ?string
    {
        return match (true) {
            str_contains($message, 'shipping') || str_contains($message, 'delivery') => 'shipping',
            str_contains($message, 'return') || str_contains($message, 'refund') => 'returns',
            str_contains($message, 'authentic') || str_contains($message, 'genuine') => 'authenticity',
            str_contains($message, 'care') || str_contains($message, 'policy') || str_contains($message, 'support') => 'care',
            default => null,
        };
    }
}
