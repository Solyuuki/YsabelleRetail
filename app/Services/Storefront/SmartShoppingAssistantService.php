<?php

namespace App\Services\Storefront;

use App\Services\Storefront\Assistant\StorefrontAssistantGuidanceService;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SmartShoppingAssistantService
{
    private const INTENT_GREETING = 'greeting';

    private const INTENT_SMALL_TALK = 'small_talk';

    private const INTENT_CART = 'ecommerce_cart';

    private const INTENT_CHECKOUT = 'ecommerce_checkout';

    private const INTENT_SUPPORT = 'ecommerce_support';

    private const INTENT_VISUAL_SEARCH = 'visual_search';

    private const INTENT_PRODUCT_SEARCH = 'ecommerce_product_search';

    private const INTENT_OUT_OF_SCOPE = 'out_of_scope';

    private const INTENT_FALLBACK = 'fallback';

    private const GREETING_PHRASES = [
        'good afternoon',
        'good evening',
        'good morning',
        'hello',
        'hello there',
        'hey',
        'hi',
        'hi there',
    ];

    private const SMALL_TALK_PHRASES = [
        'appreciate it',
        'how are you',
        'how are you doing',
        'how is it going',
        'thank you',
        'thanks',
    ];

    private const PRODUCT_KEYWORDS = [
        'boot',
        'boots',
        'catalog',
        'collection',
        'footwear',
        'pair',
        'pairs',
        'product',
        'products',
        'runner',
        'runners',
        'shoe',
        'shoes',
        'sneaker',
        'sneakers',
    ];

    private const AVAILABILITY_KEYWORDS = [
        'availability',
        'available',
        'in stock',
        'low stock',
        'sold out',
        'stock',
    ];

    private const OUT_OF_SCOPE_KEYWORDS = [
        'bitcoin',
        'capital',
        'code',
        'coding',
        'crypto',
        'football',
        'history',
        'joke',
        'math',
        'movie',
        'music',
        'news',
        'physics',
        'politics',
        'president',
        'recipe',
        'science',
        'stock market',
        'translate',
        'weather',
    ];

    public function __construct(
        private readonly ProductDiscoveryService $productDiscovery,
        private readonly CartService $cartService,
        private readonly AuthFactory $auth,
        private readonly StorefrontAssistantGuidanceService $guidance,
    ) {}

    public function respond(string $message): array
    {
        $resolution = $this->resolveMessage($message);

        return $this->guidance->complete(
            intent: $resolution['intent'],
            userMessage: $resolution['message'],
            response: $resolution['response'],
            context: $resolution['context'],
        );
    }

    public function stream(string $message): iterable
    {
        $resolution = $this->resolveMessage($message);

        return $this->guidance->stream(
            intent: $resolution['intent'],
            userMessage: $resolution['message'],
            response: $resolution['response'],
            context: $resolution['context'],
        );
    }

    private function resolveMessage(string $message): array
    {
        $message = trim($message);
        $normalized = Str::lower($message);
        $criteria = $this->productDiscovery->buildCriteriaFromText($message);
        $intent = $this->classifyIntent($message, $normalized, $criteria);

        $response = match ($intent['intent']) {
            self::INTENT_GREETING => $this->greetingResponse(),
            self::INTENT_SMALL_TALK => $this->smallTalkResponse($normalized),
            self::INTENT_CART => $this->cartResponse(),
            self::INTENT_CHECKOUT => $this->checkoutResponse(),
            self::INTENT_SUPPORT => $this->supportResponse($intent['topic'] ?? 'care'),
            self::INTENT_VISUAL_SEARCH => $this->visualSearchResponse(),
            self::INTENT_PRODUCT_SEARCH => $this->productIntentResponse($message, $normalized, $criteria),
            self::INTENT_OUT_OF_SCOPE => $this->outOfScopeResponse(),
            default => $this->clarificationResponse(),
        };

        return [
            'intent' => $intent['intent'],
            'message' => $message,
            'response' => $response,
            'context' => $this->guidanceContext(
                intent: $intent,
                criteria: $criteria,
                response: $response,
            ),
        ];
    }

    private function classifyIntent(string $message, string $normalized, array $criteria): array
    {
        if ($this->isGreetingIntent($message, $normalized, $criteria)) {
            return ['intent' => self::INTENT_GREETING];
        }

        if ($this->isSmallTalkIntent($message, $normalized, $criteria)) {
            return ['intent' => self::INTENT_SMALL_TALK];
        }

        if ($this->isCartIntent($normalized)) {
            return ['intent' => self::INTENT_CART];
        }

        if ($this->isCheckoutIntent($normalized)) {
            return ['intent' => self::INTENT_CHECKOUT];
        }

        if ($topic = $this->supportTopic($normalized)) {
            return [
                'intent' => self::INTENT_SUPPORT,
                'topic' => $topic,
            ];
        }

        if ($this->isVisualSearchIntent($normalized)) {
            return ['intent' => self::INTENT_VISUAL_SEARCH];
        }

        if ($this->hasHighConfidenceProductIntent($normalized, $criteria)) {
            return ['intent' => self::INTENT_PRODUCT_SEARCH];
        }

        if ($this->isOutOfScopeIntent($normalized, $criteria)) {
            return ['intent' => self::INTENT_OUT_OF_SCOPE];
        }

        return ['intent' => self::INTENT_FALLBACK];
    }

    private function productIntentResponse(string $message, string $normalized, array $criteria): array
    {
        if ($this->isAvailabilityIntent($normalized) && ! $this->hasStructuredProductSignal($criteria)) {
            return $this->lowStockResponse();
        }

        return $this->productResponse($message, $criteria);
    }

    private function productResponse(string $message, array $criteria): array
    {
        $matchSet = $this->productDiscovery->findMatches(
            criteria: $criteria,
            limit: 4,
        );

        $products = $matchSet['products']->map(fn ($product): array => $this->productDiscovery->formatProduct($product))->all();
        $criteria = $matchSet['criteria'];

        if ($products === []) {
            return $this->response(
                answer: 'I could not pin down the right active pair yet. Tell me the color, budget, size, or use case you want, and I will narrow the catalog for you.',
                actions: $this->defaultActions(),
            );
        }

        $answer = match (true) {
            $criteria['max_price'] !== null && $matchSet['used_fallback'] => 'I could not find an exact fit in that budget, but these are the nearest active options I would recommend right now.',
            $criteria['color'] && $criteria['category'] => 'These are the strongest active matches I found for that color and silhouette.',
            $criteria['use_case'] === 'daily' => 'These active pairs are the most versatile daily options I found in the current catalog.',
            $criteria['use_case'] === 'running' || $criteria['category'] === 'running' => 'These are the best running-focused options I found from the current active catalog.',
            str_contains(Str::lower($message), 'available') || str_contains(Str::lower($message), 'stock') => 'These are the closest active matches I found, with current stock status included.',
            default => 'These are the closest active matches I found from the current catalog.',
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

    private function sizeSupportResponse(): array
    {
        return $this->response(
            answer: 'For sizing help, tell me the product type, color, price range, or size you want and I will narrow the catalog for you.',
            actions: [
                ['label' => 'Find running shoes', 'type' => 'message', 'message' => 'Find running shoes'],
                ['label' => 'Show size 9 options', 'type' => 'message', 'message' => 'Show me size 9 shoes'],
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

    private function supportResponse(string $topic): array
    {
        if ($topic === 'size') {
            return $this->sizeSupportResponse();
        }

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

    private function greetingResponse(): array
    {
        return $this->response(
            answer: 'Welcome to Ysabelle Retail. I can help you find the right pair, check stock, review your cart, or match a shoe photo from the current catalog.',
            actions: $this->defaultActions(),
        );
    }

    private function smallTalkResponse(string $message): array
    {
        $answer = str_contains($message, 'thank')
            ? 'You are very welcome. If you want, I can keep helping with products, sizing, stock, or a similar-by-image search.'
            : 'I am ready to help with products, stock, sizing, cart, and checkout. Tell me what you are shopping for.';

        return $this->response(
            answer: $answer,
            actions: $this->defaultActions(),
        );
    }

    private function visualSearchResponse(): array
    {
        return $this->response(
            answer: 'Upload a shoe photo and I will use it as shopping context to find the closest active styles, or guide you to similar options if the exact pair is not in the catalog.',
            actions: [
                ['label' => 'Open Visual Search', 'type' => 'panel', 'target' => 'visual-search'],
                ['label' => 'Browse catalog', 'type' => 'link', 'url' => route('storefront.shop')],
            ],
        );
    }

    private function outOfScopeResponse(): array
    {
        return $this->response(
            answer: 'I can only help with Ysabelle Retail shopping support, such as products, stock, sizing, cart, checkout, and catalog image search.',
            actions: $this->defaultActions(),
        );
    }

    private function clarificationResponse(): array
    {
        return $this->response(
            answer: 'I can help with shoe recommendations, stock, sizing, cart, checkout, or image search. Tell me your preferred color, budget, size, or use case and I will guide you from there.',
            actions: $this->defaultActions(),
        );
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
            || str_contains($message, 'upload')
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

    private function isAvailabilityIntent(string $message): bool
    {
        return $this->containsAny($message, self::AVAILABILITY_KEYWORDS);
    }

    private function supportTopic(string $message): ?string
    {
        return match (true) {
            str_contains($message, 'shipping') || str_contains($message, 'delivery') => 'shipping',
            str_contains($message, 'return') || str_contains($message, 'refund') => 'returns',
            str_contains($message, 'authentic') || str_contains($message, 'genuine') => 'authenticity',
            str_contains($message, 'size') || str_contains($message, 'fit') || str_contains($message, 'true to size') => 'size',
            str_contains($message, 'care') || str_contains($message, 'policy') || str_contains($message, 'support') => 'care',
            default => null,
        };
    }

    private function isGreetingIntent(string $message, string $normalized, array $criteria): bool
    {
        if ($this->hasDomainSignal($normalized, $criteria)) {
            return false;
        }

        $simplified = $this->simplifyMessage($message);

        if (in_array($simplified, self::GREETING_PHRASES, true)) {
            return true;
        }

        return $this->startsWithAny($simplified, ['hello ', 'hey ', 'hi ']) && $this->tokenCount($simplified) <= 3;
    }

    private function isSmallTalkIntent(string $message, string $normalized, array $criteria): bool
    {
        if ($this->hasDomainSignal($normalized, $criteria)) {
            return false;
        }

        $simplified = $this->simplifyMessage($message);

        return in_array($simplified, self::SMALL_TALK_PHRASES, true);
    }

    private function hasHighConfidenceProductIntent(string $message, array $criteria): bool
    {
        return $this->hasStructuredProductSignal($criteria) || $this->hasProductKeywordMatch($message);
    }

    private function hasStructuredProductSignal(array $criteria): bool
    {
        return filled($criteria['category'])
            || filled($criteria['color'])
            || filled($criteria['size'])
            || filled($criteria['use_case'])
            || $criteria['max_price'] !== null
            || $criteria['min_price'] !== null;
    }

    private function hasProductKeywordMatch(string $message): bool
    {
        return $this->containsAny($message, self::PRODUCT_KEYWORDS);
    }

    private function isOutOfScopeIntent(string $message, array $criteria): bool
    {
        if ($this->hasDomainSignal($message, $criteria)) {
            return false;
        }

        if ($this->containsAny($message, self::OUT_OF_SCOPE_KEYWORDS)) {
            return true;
        }

        return (bool) preg_match('/^(what|who|when|where|why|how|tell|explain|solve|write)\b/i', $message);
    }

    private function hasDomainSignal(string $message, array $criteria): bool
    {
        return $this->hasStructuredProductSignal($criteria)
            || $this->hasProductKeywordMatch($message)
            || $this->isCartIntent($message)
            || $this->isCheckoutIntent($message)
            || $this->isVisualSearchIntent($message)
            || $this->isAvailabilityIntent($message)
            || $this->supportTopic($message) !== null;
    }

    private function containsAny(string $message, array $phrases): bool
    {
        foreach ($phrases as $phrase) {
            if (str_contains($message, $phrase)) {
                return true;
            }
        }

        return false;
    }

    private function startsWithAny(string $message, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($message, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function simplifyMessage(string $message): string
    {
        $normalized = Str::lower($message);
        $normalized = preg_replace('/[^a-z0-9\s]+/i', ' ', $normalized) ?? $normalized;

        return trim((string) preg_replace('/\s+/', ' ', $normalized));
    }

    private function tokenCount(string $message): int
    {
        if ($message === '') {
            return 0;
        }

        return count(explode(' ', $message));
    }

    private function guidanceContext(array $intent, array $criteria, array $response): array
    {
        return [
            'intent' => $intent['intent'],
            'topic' => $intent['topic'] ?? null,
            'criteria' => Arr::only($criteria, [
                'brand_style',
                'category',
                'color',
                'max_price',
                'min_price',
                'size',
                'use_case',
            ]),
            'products' => collect($response['products'] ?? [])
                ->map(fn (array $product): array => Arr::only($product, [
                    'name',
                    'category',
                    'price_label',
                    'availability',
                    'short_description',
                ]))
                ->values()
                ->all(),
            'actions' => collect($response['actions'] ?? [])
                ->map(fn (array $action): array => Arr::only($action, ['label', 'type', 'message', 'target', 'url']))
                ->values()
                ->all(),
        ];
    }
}
