<?php

namespace App\Services\Storefront;

use App\Services\Storefront\Assistant\StorefrontAssistantGuidanceService;
use App\Services\Storefront\Assistant\StorefrontAssistantContextResolver;
use App\Services\Storefront\Assistant\StorefrontCommerceQueryParser;
use App\Services\Storefront\Assistant\StorefrontAssistantIntentRouter;
use App\Services\Storefront\Assistant\StorefrontAssistantMessageNormalizer;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SmartShoppingAssistantService
{
    public function __construct(
        private readonly ProductDiscoveryService $productDiscovery,
        private readonly CartService $cartService,
        private readonly AuthFactory $auth,
        private readonly StorefrontAssistantGuidanceService $guidance,
        private readonly StorefrontAssistantContextResolver $assistantContextResolver,
        private readonly StorefrontAssistantIntentRouter $intentRouter,
        private readonly StorefrontAssistantMessageNormalizer $messageNormalizer,
        private readonly StorefrontCommerceQueryParser $commerceQueryParser,
        private readonly StoreSupportKnowledgeService $supportKnowledge,
    ) {}

    public function respond(string $message, array $assistantContext = [], array $pageContext = []): array
    {
        $resolution = $this->resolveMessage($message, $assistantContext, $pageContext);

        return $this->guidance->complete(
            intent: $resolution['intent'],
            userMessage: $resolution['message'],
            response: $resolution['response'],
            context: $resolution['context'],
        );
    }

    public function stream(string $message, array $assistantContext = [], array $pageContext = []): iterable
    {
        $resolution = $this->resolveMessage($message, $assistantContext, $pageContext);

        return $this->guidance->stream(
            intent: $resolution['intent'],
            userMessage: $resolution['message'],
            response: $resolution['response'],
            context: $resolution['context'],
        );
    }

    private function resolveMessage(string $message, array $assistantContext = [], array $pageContext = []): array
    {
        $message = trim($message);
        $assistantContext = $this->assistantContextResolver->sanitize($assistantContext);
        $normalizedMessage = $this->messageNormalizer->normalize($message, $assistantContext);
        $commerce = $this->commerceQueryParser->parse($message, $pageContext);
        $criteria = $this->productDiscovery->buildCriteriaFromText($message, $commerce);
        $intent = $this->intentRouter->classify($normalizedMessage['normalized'], $criteria, $commerce);
        $intent = $this->assistantContextResolver->recoverIntent($intent, $normalizedMessage, $assistantContext) ?? $intent;

        $response = match ($intent['intent']) {
            StorefrontAssistantIntentRouter::INTENT_GREETING => $this->greetingResponse(),
            StorefrontAssistantIntentRouter::INTENT_SMALL_TALK => $this->smallTalkResponse($normalizedMessage['normalized']),
            StorefrontAssistantIntentRouter::INTENT_CART => $this->cartResponse(),
            StorefrontAssistantIntentRouter::INTENT_CHECKOUT,
            StorefrontAssistantIntentRouter::INTENT_SUPPORT,
            StorefrontAssistantIntentRouter::INTENT_SUPPORT_SHIPPING,
            StorefrontAssistantIntentRouter::INTENT_SUPPORT_RETURNS,
            StorefrontAssistantIntentRouter::INTENT_SUPPORT_CONTACT,
            StorefrontAssistantIntentRouter::INTENT_SUPPORT_SIZE_GUIDE,
            StorefrontAssistantIntentRouter::INTENT_SUPPORT_LOCATION,
            StorefrontAssistantIntentRouter::INTENT_SUPPORT_LOGIN,
            StorefrontAssistantIntentRouter::INTENT_SUPPORT_SIGNUP,
            StorefrontAssistantIntentRouter::INTENT_SUPPORT_AUTH_QUICK_OPTIONS,
            StorefrontAssistantIntentRouter::INTENT_SUPPORT_AUTH_PHONE_OPTION_STATUS,
            StorefrontAssistantIntentRouter::INTENT_SUPPORT_AUTH_MAGIC_LINK_STATUS,
            StorefrontAssistantIntentRouter::INTENT_SUPPORT_TOPIC_OPTIONS,
            StorefrontAssistantIntentRouter::INTENT_GUIDANCE_SITE_USE,
            StorefrontAssistantIntentRouter::INTENT_GUIDANCE_IMAGE_SEARCH,
            StorefrontAssistantIntentRouter::INTENT_GUIDANCE_ORDERING_FLOW => $this->supportKnowledge->responseForIntent(
                $intent['intent'],
                $intent['topic'] ?? null,
                $assistantContext,
            ),
            StorefrontAssistantIntentRouter::INTENT_VISUAL_SEARCH => $this->visualSearchResponse(),
            StorefrontAssistantIntentRouter::INTENT_PRODUCT_SEARCH => $this->productIntentResponse(
                $message,
                $normalizedMessage['normalized'],
                $criteria,
                $commerce,
                $pageContext,
            ),
            StorefrontAssistantIntentRouter::INTENT_OUT_OF_SCOPE => $this->outOfScopeResponse(),
            default => $this->clarificationResponse(
                $this->assistantContextResolver->shouldUseSupportClarifier($normalizedMessage, $assistantContext)
            ),
        };

        $response['assistant_context'] = $this->assistantContextResolver->buildResponseContext(
            $intent,
            $response,
            $assistantContext,
        );

        return [
            'intent' => $intent['intent'],
            'message' => $message,
            'response' => $response,
            'context' => $this->guidanceContext(
                intent: $intent,
                criteria: $criteria,
                commerce: $commerce,
                response: $response,
            ),
        ];
    }

    private function productIntentResponse(string $message, string $normalized, array $criteria, array $commerce, array $pageContext = []): array
    {
        $directLookup = $this->productDiscovery->findDirectProductMatch($message, $pageContext, $commerce);

        if (($commerce['intent'] ?? null) === 'size_stock' && filled($commerce['entities']['size'] ?? null)) {
            return $this->sizeStockResponse($commerce, $directLookup);
        }

        if (($directLookup['status'] ?? null) === 'current_product' && ($directLookup['product'] ?? null)) {
            return $this->response(
                answer: 'You are currently viewing '.$directLookup['product']->name.'.',
                products: [$this->productDiscovery->formatProduct($directLookup['product'])],
                actions: [
                    ['label' => 'Open full catalog', 'type' => 'link', 'url' => route('storefront.shop')],
                    ['label' => 'Check my cart', 'type' => 'message', 'message' => 'What is in my cart?'],
                ],
            );
        }

        if (($directLookup['status'] ?? null) === 'active_match' && ($directLookup['product'] ?? null)) {
            return $this->response(
                answer: $this->exactProductAnswer($message, $directLookup['product']->name),
                products: [$this->productDiscovery->formatProduct($directLookup['product'])],
                actions: [
                    ['label' => 'Open full catalog', 'type' => 'link', 'url' => route('storefront.shop')],
                    ['label' => 'Check my cart', 'type' => 'message', 'message' => 'What is in my cart?'],
                ],
            );
        }

        if (($directLookup['status'] ?? null) === 'active_close_match' && ($directLookup['product'] ?? null)) {
            return $this->closeProductMatchResponse($directLookup['product']->name, $directLookup['product']);
        }

        if (($directLookup['status'] ?? null) === 'inactive_match') {
            return $this->inactiveExactProductResponse($criteria, $directLookup);
        }

        if ($this->isAvailabilityIntent($normalized) && ! $this->hasStructuredProductSignal($criteria)) {
            return $this->lowStockResponse();
        }

        return $this->productResponse($message, $criteria, $commerce, $directLookup);
    }

    private function productResponse(string $message, array $criteria, array $commerce, array $directLookup = []): array
    {
        $matchSet = $this->productDiscovery->findMatches(
            criteria: $criteria,
            limit: 4,
        );

        $products = $matchSet['products']->map(fn ($product): array => $this->productDiscovery->formatProduct($product))->all();
        $criteria = $matchSet['criteria'];

        if ($products === []) {
            return $this->response(
                answer: filled($directLookup['query'] ?? null)
                    ? 'I could not find that product in the active catalog. Tell me the color, budget, size, or use case you want, and I will narrow the catalog for you.'
                    : 'I could not pin down the right active pair yet. Tell me the color, budget, size, or use case you want, and I will narrow the catalog for you.',
                actions: $this->defaultActions(),
            );
        }

        $answer = match (true) {
            ($commerce['flags']['affordable'] ?? false) === true && $criteria['max_price'] === null => 'These are the most affordable active pairs I found right now.',
            filled($directLookup['query'] ?? null) => 'I did not find an exact match, but these are the closest real products I found.',
            $criteria['max_price'] !== null && $matchSet['used_fallback'] => 'I could not find an exact fit in that budget, but these are the nearest active options I would recommend right now.',
            $criteria['color'] && $criteria['category'] => 'These are the strongest active matches I found for that color and silhouette.',
            $criteria['use_case'] === 'hiking' || str_contains(Str::lower($message), 'hiking') => 'These are the strongest hiking-oriented options I found in the active catalog.',
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

    private function inactiveExactProductResponse(array $criteria, array $directLookup): array
    {
        $query = (string) ($directLookup['query'] ?? $directLookup['product']?->name ?? 'that product');
        $suggestions = $this->productDiscovery->findMatches([
            ...$criteria,
            'search' => $query,
        ], 3)['products']
            ->filter(fn ($product) => $product->id !== ($directLookup['product']->id ?? null))
            ->map(fn ($product): array => $this->productDiscovery->formatProduct($product))
            ->values()
            ->all();

        return $this->response(
            answer: $query.' exists in the catalog, but it is not currently available in the active storefront.',
            products: $suggestions,
            actions: [
                ['label' => 'Open full catalog', 'type' => 'link', 'url' => route('storefront.shop')],
                ['label' => 'Find running shoes', 'type' => 'message', 'message' => 'Find running shoes'],
            ],
        );
    }

    private function closeProductMatchResponse(string $productName, $product): array
    {
        return $this->response(
            answer: 'I did not find an exact match, but '.$productName.' is the closest real product name match I found.',
            products: [$this->productDiscovery->formatProduct($product)],
            actions: [
                ['label' => 'Open full catalog', 'type' => 'link', 'url' => route('storefront.shop')],
                ['label' => 'Check my cart', 'type' => 'message', 'message' => 'What is in my cart?'],
            ],
        );
    }

    private function sizeStockResponse(array $commerce, array $directLookup): array
    {
        $size = (string) ($commerce['entities']['size'] ?? '');
        $product = $directLookup['product'] ?? null;

        if (! $product) {
            return $this->response(
                answer: 'Tell me which product you want to check, and I will verify whether size '.$size.' is available.',
                actions: $this->defaultActions(),
            );
        }

        if (($directLookup['status'] ?? null) === 'inactive_match') {
            return $this->response(
                answer: $product->name.' exists in the catalog, but it is not currently available in the active storefront.',
                products: [$this->productDiscovery->formatProduct($product)],
                actions: [
                    ['label' => 'Open full catalog', 'type' => 'link', 'url' => route('storefront.shop')],
                    ['label' => 'Find running shoes', 'type' => 'message', 'message' => 'Find running shoes'],
                ],
            );
        }

        $availability = $this->productDiscovery->sizeAvailabilityForProduct($product, $size);
        $availableSizes = $availability['available_sizes'];
        $availableLabel = $availableSizes === []
            ? 'No sizes are currently available.'
            : 'Available sizes right now: '.implode(', ', $availableSizes).'.';

        $answer = match (true) {
            $availability['is_available'] => 'Yes, '.$product->name.' is available in size '.$availability['requested_size'].' right now.',
            $availability['has_size'] => $product->name.' has size '.$availability['requested_size'].', but it is currently sold out. '.$availableLabel,
            default => 'I could not find size '.$availability['requested_size'].' for '.$product->name.'. '.$availableLabel,
        };

        return $this->response(
            answer: $answer,
            products: [$this->productDiscovery->formatProduct($product)],
            actions: [
                ['label' => 'Open full catalog', 'type' => 'link', 'url' => route('storefront.shop')],
                ['label' => 'Check my cart', 'type' => 'message', 'message' => 'What is in my cart?'],
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
                    ['label' => 'Shoes under PHP 3,000', 'type' => 'message', 'message' => 'Show me shoes under 3000'],
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
            .' worth PHP '.number_format((float) $summary['total'], 0).'.';

        if ($summary['shipping'] > 0) {
            $answer .= ' Shipping is PHP '.number_format((float) $summary['shipping'], 0).' until you reach the free-shipping threshold.';
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
            answer: 'I can only help with Ysabelle Retail Shop support, products, cart, checkout, and catalog image search.',
            actions: $this->defaultActions(),
        );
    }

    private function clarificationResponse(bool $supportClarifier = false): array
    {
        return $this->response(
            answer: $supportClarifier
                ? 'Are you asking about login options, checkout options, or contact/location details?'
                : 'I can help with shoe recommendations, stock, sizing, cart, checkout, or image search. Tell me your preferred color, budget, size, or use case and I will guide you from there.',
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

    private function isAvailabilityIntent(string $message): bool
    {
        return $this->containsAny($message, [
            'availability',
            'available',
            'in stock',
            'low stock',
            'sold out',
            'stock',
        ]);
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

    private function containsAny(string $message, array $phrases): bool
    {
        foreach ($phrases as $phrase) {
            if (str_contains($message, $phrase)) {
                return true;
            }
        }

        return false;
    }

    private function exactProductAnswer(string $message, string $productName): string
    {
        return 'Yes — I found '.$productName.'.';
    }

    private function displayLookupQuery(string $query): string
    {
        return Str::of($query)
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->title()
            ->value();
    }

    private function guidanceContext(array $intent, array $criteria, array $commerce, array $response): array
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
                'product_name',
                'size',
                'use_case',
            ]),
            'commerce' => Arr::only($commerce['entities'] ?? [], [
                'budget_max',
                'budget_min',
                'category',
                'color',
                'product_name',
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
