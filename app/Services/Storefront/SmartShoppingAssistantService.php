<?php

namespace App\Services\Storefront;

use App\Models\Catalog\Product;
use App\Services\Catalog\ProductAvailabilityService;
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
        private readonly ProductAvailabilityService $availability,
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
        $pageContext = $this->assistantContextResolver->conversationProductContext($assistantContext, $pageContext);
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
            StorefrontAssistantIntentRouter::INTENT_PRODUCT_PRICE_RANKING => $this->mostExpensiveProductResponse($criteria),
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
            $pageContext,
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
        $isStockIntent = $this->isStockIntent($normalized, $commerce);

        if (
            ($directLookup['product'] ?? null) === null
            && $isStockIntent
        ) {
            $currentProduct = $this->productDiscovery->currentProductFromContext($pageContext);

            if ($currentProduct) {
                $directLookup = [
                    'status' => 'current_product',
                    'product' => $currentProduct,
                    'query' => $currentProduct->name,
                    'match_type' => 'current_product',
                ];
            }
        }

        if (($directLookup['status'] ?? null) === 'current_product' && ($directLookup['product'] ?? null) && $isStockIntent) {
            return $this->currentProductStockResponse(
                $message,
                $commerce,
                $pageContext,
                $directLookup['product'],
            );
        }

        if (($commerce['intent'] ?? null) === 'size_stock' && filled($commerce['entities']['size'] ?? null)) {
            return $this->sizeStockResponse($message, $commerce, $directLookup);
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
            if ($isStockIntent) {
                $requestedColor = $this->resolveProductColorFromMessage(
                    $directLookup['product'],
                    $commerce['entities']['color'] ?? null,
                    $message,
                );
                $requestedSize = trim((string) ($commerce['entities']['size'] ?? ''));

                if ($requestedSize !== '') {
                    return $this->response(
                        answer: $this->currentProductSizeStockAnswer($directLookup['product'], $requestedSize, $requestedColor),
                        products: [$this->productDiscovery->formatProduct($directLookup['product'])],
                        actions: [
                            ['label' => 'Open full catalog', 'type' => 'link', 'url' => route('storefront.shop')],
                            ['label' => 'Check my cart', 'type' => 'message', 'message' => 'What is in my cart?'],
                        ],
                    );
                }
            }

            return $this->response(
                answer: $this->stockTruthAnswer($directLookup['product']),
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
            if ($isStockIntent) {
                return $this->response(
                    answer: ((string) ($directLookup['query'] ?? $directLookup['product']?->name ?? 'That product')).' is currently unavailable.',
                    actions: [
                        ['label' => 'Open full catalog', 'type' => 'link', 'url' => route('storefront.shop')],
                        ['label' => 'Check my cart', 'type' => 'message', 'message' => 'What is in my cart?'],
                    ],
                );
            }

            return $this->inactiveExactProductResponse($criteria, $directLookup);
        }

        if (
            ($directLookup['product'] ?? null) === null
            && $this->isLowStockListingIntent($normalized)
            && ! $this->hasStructuredProductSignal($criteria)
        ) {
            return $this->lowStockResponse();
        }

        if (
            ($directLookup['product'] ?? null) === null
            && $this->isOutOfStockListingIntent($normalized)
            && ! $this->hasStructuredProductSignal($criteria)
        ) {
            return $this->outOfStockResponse();
        }

        if (
            ($directLookup['product'] ?? null) === null
            && $isStockIntent
            && $this->isGenericStockLookupQuery($directLookup['query'] ?? null)
        ) {
            return $this->response(
                answer: 'Tell me which product you want to check, and I will verify the stock for you.',
                actions: $this->defaultActions(),
            );
        }

        if (
            ($directLookup['product'] ?? null) === null
            && $isStockIntent
            && ! filled($criteria['product_name'] ?? null)
            && filled($directLookup['query'] ?? null)
        ) {
            return $this->response(
                answer: 'I could not find that product in the active catalog. Tell me the exact product name or open its product page and I will check the stock for you.',
                actions: [
                    ['label' => 'Open full catalog', 'type' => 'link', 'url' => route('storefront.shop')],
                    ['label' => 'Check my cart', 'type' => 'message', 'message' => 'What is in my cart?'],
                ],
            );
        }

        if (
            ($directLookup['product'] ?? null) === null
            && $isStockIntent
            && ! $this->hasStructuredProductSignal($criteria)
        ) {
            return $this->response(
                answer: 'Tell me which product you want to check, and I will verify the stock for you.',
                actions: $this->defaultActions(),
            );
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

    private function mostExpensiveProductResponse(array $criteria): array
    {
        $ranking = $this->productDiscovery->findMostExpensiveProducts($criteria, 4);
        $matches = $ranking['matches'];

        if ($matches->isEmpty()) {
            return $this->response(
                answer: 'I could not find an active priced product that matches that request right now.',
                actions: $this->defaultActions(),
            );
        }

        $topMatch = $matches->first();
        $topProduct = $topMatch['product'];
        $topAvailabilityLabel = (string) ($topMatch['availability']['label'] ?? 'Currently Unavailable');
        $topPrice = (float) ($topMatch['highest_price'] ?? 0);
        $startingPrice = (float) $topProduct->base_price;
        $products = $matches
            ->map(function (array $match): array {
                $product = $this->productDiscovery->formatProduct($match['product']);
                $product['price_ranking'] = [
                    'highest_active_price' => (float) ($match['highest_price'] ?? 0),
                    'highest_active_price_label' => $this->phpMoneyLabel((float) ($match['highest_price'] ?? 0)),
                    'stock_label' => (string) ($match['availability']['label'] ?? 'Currently Unavailable'),
                    'stock_state' => (string) ($match['availability']['state'] ?? ProductAvailabilityService::STATE_OUT_OF_STOCK),
                ];

                return $product;
            })
            ->all();

        $answer = ($ranking['all_out_of_stock'] ?? false) === true
            ? 'All matching active products are currently out of stock. '
            : '';

        $answer .= sprintf(
            '%s is the highest-priced active catalog match right now at %s.',
            $topProduct->name,
            $this->phpMoneyLabel($topPrice),
        );

        if ($startingPrice > 0 && abs($startingPrice - $topPrice) > 0.009) {
            $answer .= ' It starts at '.$this->phpMoneyLabel($startingPrice).'.';
        }

        $answer .= ' Category: '.($topProduct->category?->name ?? 'Collection').'.';
        $answer .= ' Stock status: '.$topAvailabilityLabel.'.';

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
            answer: $query.' is currently unavailable.',
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
            answer: 'I did not find an exact match, but '.$productName.' is the closest real product name match I found. '.$this->stockTruthAnswer($product),
            products: [$this->productDiscovery->formatProduct($product)],
            actions: [
                ['label' => 'Open full catalog', 'type' => 'link', 'url' => route('storefront.shop')],
                ['label' => 'Check my cart', 'type' => 'message', 'message' => 'What is in my cart?'],
            ],
        );
    }

    private function currentProductStockResponse(string $message, array $commerce, array $pageContext, Product $product): array
    {
        $currentProductContext = data_get($pageContext, 'current_product', []);
        $requestedColor = $this->resolveCurrentProductColor($product, $commerce, $currentProductContext, $message);
        $requestedSize = $this->resolveCurrentProductSize($commerce, $currentProductContext);

        if ($requestedSize !== null) {
            if ($requestedColor === null) {
                return $this->response(
                    answer: $this->currentProductSizeAvailabilityAnswer($product, $requestedSize),
                    products: [$this->productDiscovery->formatProduct($product)],
                    actions: [
                        ['label' => 'Open full catalog', 'type' => 'link', 'url' => route('storefront.shop')],
                        ['label' => 'Check my cart', 'type' => 'message', 'message' => 'What is in my cart?'],
                    ],
                );
            }

            return $this->response(
                answer: $this->currentProductSizeStockAnswer($product, $requestedSize, $requestedColor),
                products: [$this->productDiscovery->formatProduct($product)],
                actions: [
                    ['label' => 'Open full catalog', 'type' => 'link', 'url' => route('storefront.shop')],
                    ['label' => 'Check my cart', 'type' => 'message', 'message' => 'What is in my cart?'],
                ],
            );
        }

        if ($requestedColor !== null) {
            return $this->response(
                answer: $product->name.' '.$requestedColor['color_label'].' is selected. Please choose a size so I can check the exact stock.',
                products: [$this->productDiscovery->formatProduct($product)],
                actions: [
                    ['label' => 'Open full catalog', 'type' => 'link', 'url' => route('storefront.shop')],
                    ['label' => 'Check my cart', 'type' => 'message', 'message' => 'What is in my cart?'],
                ],
            );
        }

        $availability = $this->availability->forProduct($product);
        $quantity = max(0, (int) ($availability['available_quantity'] ?? 0));

        return $this->response(
            answer: $quantity > 0
                ? $product->name.' has '.$quantity.' '.Str::plural('pair', $quantity).' across variants. Select a size for exact availability.'
                : $product->name.' is currently unavailable.',
            products: [$this->productDiscovery->formatProduct($product)],
            actions: [
                ['label' => 'Open full catalog', 'type' => 'link', 'url' => route('storefront.shop')],
                ['label' => 'Check my cart', 'type' => 'message', 'message' => 'What is in my cart?'],
            ],
        );
    }

    private function sizeStockResponse(string $message, array $commerce, array $directLookup): array
    {
        $size = (string) ($commerce['entities']['size'] ?? '');
        $product = $directLookup['product'] ?? $this->productDiscovery->inferNamedProductFromMessage($message);

        if (! $product) {
            return $this->response(
                answer: 'Tell me which product you want to check, and I will verify whether size '.$size.' is available.',
                actions: $this->defaultActions(),
            );
        }

        if (($directLookup['status'] ?? null) === 'inactive_match') {
            return $this->response(
                answer: $product->name.' is currently unavailable.',
                products: [$this->productDiscovery->formatProduct($product)],
                actions: [
                    ['label' => 'Open full catalog', 'type' => 'link', 'url' => route('storefront.shop')],
                    ['label' => 'Find running shoes', 'type' => 'message', 'message' => 'Find running shoes'],
                ],
            );
        }

        $resolvedColor = $this->resolveProductColorFromMessage($product, $commerce['entities']['color'] ?? null, $message);
        $resolvedColorLabel = $resolvedColor['color_label'] ?? null;
        $availability = $this->productDiscovery->sizeAvailabilityForProduct($product, $size, $resolvedColorLabel);
        $availableSizes = $availability['available_sizes'];
        $availableLabel = $availableSizes === []
            ? 'No sizes are currently available.'
            : 'Available sizes right now: '.implode(', ', $availableSizes).'.';
        $sizeDescriptor = $product->name.' size '.$availability['requested_size'];

        if (filled($availability['requested_color_label'] ?? null)) {
            $sizeDescriptor .= ' in '.$availability['requested_color_label'];
        }

        $answer = match (true) {
            ($availability['state'] ?? null) === ProductAvailabilityService::STATE_IN_STOCK => $sizeDescriptor.' is in stock.',
            ($availability['state'] ?? null) === ProductAvailabilityService::STATE_LOW_STOCK => $sizeDescriptor.' is available in limited stock.',
            $availability['is_backorder'] => $sizeDescriptor.' is available for backorder right now.',
            ($availability['has_variant'] ?? false) === true => $sizeDescriptor.' is currently unavailable. '.$availableLabel,
            ($availability['has_size'] ?? false) === true && filled($resolvedColorLabel) => $product->name.' has size '.$availability['requested_size'].', but not in '.$resolvedColorLabel.'. '.$availableLabel,
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

    private function currentProductSizeStockAnswer(Product $product, string $size, ?array $requestedColor = null): string
    {
        $availability = $this->productDiscovery->sizeAvailabilityForProduct(
            $product,
            $size,
            $requestedColor['color_label'] ?? null,
        );

        $variantOption = $this->currentProductVariantOption($product, $availability, $requestedColor);
        $requestedSize = (string) ($availability['requested_size'] ?? $size);
        $availableQuantity = max(0, (int) ($variantOption['available_quantity'] ?? $availability['available_quantity'] ?? 0));
        $colorLabel = $requestedColor['color_label'] ?? $availability['requested_color_label'] ?? null;
        $descriptor = $product->name;

        if (filled($colorLabel)) {
            $descriptor .= ' '.$colorLabel;
        }

        $descriptor .= ' size '.$requestedSize;

        if (($variantOption['has_variant'] ?? $availability['has_variant'] ?? false) !== true) {
            if (($availability['has_size'] ?? false) === true && filled($colorLabel)) {
                return $product->name.' has size '.$requestedSize.', but not in '.$colorLabel.'.';
            }

            return 'I could not find size '.$requestedSize.' for '.$product->name.'.';
        }

        if ($availableQuantity <= 0) {
            return $descriptor.' is currently unavailable.';
        }

        return $descriptor.' has '.$availableQuantity.' '.Str::plural('pair', $availableQuantity).' left.';
    }

    private function currentProductSizeAvailabilityAnswer(Product $product, string $size): string
    {
        $availability = $this->productDiscovery->sizeAvailabilityForProduct($product, $size);
        $descriptor = $product->name.' size '.($availability['requested_size'] ?? $size);

        return match (true) {
            ($availability['state'] ?? null) === ProductAvailabilityService::STATE_IN_STOCK => $descriptor.' is in stock.',
            ($availability['state'] ?? null) === ProductAvailabilityService::STATE_LOW_STOCK => $descriptor.' is available in limited stock.',
            ($availability['is_backorder'] ?? false) === true => $descriptor.' is available for backorder right now.',
            ($availability['has_variant'] ?? false) === true => $descriptor.' is currently unavailable.',
            default => 'I could not find size '.($availability['requested_size'] ?? $size).' for '.$product->name.'.',
        };
    }

    private function currentProductVariantOption(Product $product, array $availability, ?array $requestedColor = null): array
    {
        $requestedSize = (string) ($availability['requested_size'] ?? '');
        $requestedColorKey = $requestedColor['color_key'] ?? null;

        if ($requestedSize === '') {
            return [];
        }

        return collect($this->availability->variantOptionsForProductSize($product, $requestedSize))
            ->first(function (array $option) use ($requestedColorKey, $availability): bool {
                if ($requestedColorKey === null) {
                    return true;
                }

                return (string) ($option['color'] ?? '') === $requestedColorKey
                    || (string) ($option['color_label'] ?? '') === (string) ($availability['requested_color_label'] ?? '');
            }) ?? [];
    }

    private function resolveCurrentProductColor(Product $product, array $commerce, array $currentProductContext, string $message): ?array
    {
        $contextColor = $currentProductContext['selected_color_label']
            ?? $currentProductContext['selected_color']
            ?? null;

        return $this->resolveProductColorFromMessage(
            $product,
            $commerce['entities']['color'] ?? $contextColor,
            $message,
        );
    }

    private function resolveCurrentProductSize(array $commerce, array $currentProductContext): ?string
    {
        $size = trim((string) ($commerce['entities']['size'] ?? $currentProductContext['selected_size'] ?? ''));

        return $size !== '' ? $size : null;
    }

    private function lowStockResponse(): array
    {
        $matches = $this->productDiscovery->lowStockVariantOptions(4);
        $products = $matches
            ->map(fn (array $match): array => $this->productDiscovery->formatProduct($match['product']))
            ->unique('slug')
            ->all();

        if ($products === []) {
            return $this->response(
                answer: 'No visible products are currently low stock.',
                actions: [
                    ['label' => 'Browse all shoes', 'type' => 'link', 'url' => route('storefront.shop')],
                    ['label' => 'Black sneakers', 'type' => 'message', 'message' => 'Show me black sneakers'],
                ],
            );
        }

        $descriptors = $matches
            ->map(fn (array $match): string => $this->variantDescriptor($match['product'], $match['option']).' is available in limited stock.')
            ->values()
            ->all();

        return $this->response(
            answer: implode(' ', $descriptors),
            products: $products,
            actions: [
                ['label' => 'Open catalog', 'type' => 'link', 'url' => route('storefront.shop')],
                ['label' => 'Show my cart', 'type' => 'message', 'message' => 'What is in my cart?'],
            ],
        );
    }

    private function outOfStockResponse(): array
    {
        $matches = $this->productDiscovery->outOfStockVariantOptions(4);
        $products = $matches
            ->map(fn (array $match): array => $this->productDiscovery->formatProduct($match['product']))
            ->unique('slug')
            ->all();

        if ($products === []) {
            return $this->response(
                answer: 'No visible products are currently out of stock.',
                actions: [
                    ['label' => 'Browse all shoes', 'type' => 'link', 'url' => route('storefront.shop')],
                    ['label' => 'Black sneakers', 'type' => 'message', 'message' => 'Show me black sneakers'],
                ],
            );
        }

        $descriptors = $matches
            ->map(fn (array $match): string => $this->variantDescriptor($match['product'], $match['option']).' is currently unavailable.')
            ->values()
            ->all();

        return $this->response(
            answer: implode(' ', $descriptors),
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

        if (($summary['has_inventory_issues'] ?? false) === true) {
            $answer .= ' Some cart items need attention because inventory changed.';
        }

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
            'limited stock',
            'out of stock',
            'sold out',
            'stock',
            'stocks',
            'unavailable',
            'left',
            'remaining',
            'natitira',
            'may stock',
            'meron pa',
        ]);
    }

    private function isQuantityIntent(string $message): bool
    {
        return $this->containsAny($message, [
            'how many',
            'ilan',
            'pairs left',
            'pair left',
            'stocks left',
            'stock left',
            'remaining',
            'natitira',
        ]);
    }

    private function isStockIntent(string $message, array $commerce): bool
    {
        return ($commerce['flags']['stock_intent'] ?? false) === true
            || $this->isAvailabilityIntent($message)
            || $this->isQuantityIntent($message);
    }

    private function isGenericStockLookupQuery(mixed $query): bool
    {
        $normalized = Str::of((string) $query)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/i', ' ')
            ->squish()
            ->value();

        if ($normalized === '') {
            return true;
        }

        return in_array($normalized, [
            'availability',
            'available',
            'im talking about stocks',
            'i m talking about stocks',
            'left',
            'remaining',
            'sabi ko stock',
            'stock',
            'stocks',
        ], true);
    }

    private function hasStructuredProductSignal(array $criteria): bool
    {
        if (filled($criteria['product_name'] ?? null)) {
            return true;
        }

        return filled($criteria['category'])
            || filled($criteria['color'])
            || filled($criteria['size'])
            || filled($criteria['use_case'])
            || $criteria['max_price'] !== null
            || $criteria['min_price'] !== null;
    }

    private function isLowStockListingIntent(string $message): bool
    {
        return $this->containsAny($message, [
            'low stock',
            'limited stock',
        ]);
    }

    private function isOutOfStockListingIntent(string $message): bool
    {
        return $this->containsAny($message, [
            'no stock',
            'out of stock',
            'sold out',
            'unavailable',
            'wala bang stock',
        ]);
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

    private function stockTruthAnswer(Product $product, bool $quantityIntent = false): string
    {
        $availability = $this->availability->forProduct($product);

        return match ($availability['state'] ?? null) {
            ProductAvailabilityService::STATE_INACTIVE => $product->name.' is currently unavailable.',
            ProductAvailabilityService::STATE_BACKORDER_AVAILABLE => $product->name.' is available for backorder right now.',
            ProductAvailabilityService::STATE_OUT_OF_STOCK => $product->name.' is currently unavailable.',
            ProductAvailabilityService::STATE_LOW_STOCK => $product->name.' is available in limited stock.',
            ProductAvailabilityService::STATE_IN_STOCK => $product->name.' is in stock right now.',
            default => $product->name.' is available right now.',
        };
    }

    private function pairLabel(int $quantity): string
    {
        return $quantity.' '.Str::plural('pair', $quantity);
    }

    private function phpMoneyLabel(float $amount): string
    {
        return 'PHP '.number_format($amount, 0);
    }

    private function variantDescriptor(Product $product, array $option): string
    {
        $descriptor = $product->name;
        $size = trim((string) ($option['size'] ?? ''));
        $color = trim((string) ($option['color_label'] ?? $option['color'] ?? ''));

        if ($size !== '') {
            $descriptor .= ' size '.$size;
        }

        if ($color !== '') {
            $descriptor .= ' in '.$color;
        }

        return $descriptor;
    }

    private function resolveProductColorFromMessage(Product $product, ?string $commerceColor, string $message): ?array
    {
        foreach (array_filter([$commerceColor, $message]) as $candidate) {
            $resolved = $this->availability->resolveColorOptionForProduct($product, (string) $candidate);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        $normalizedMessage = $this->normalizeColorMatchText($message);

        return collect($this->availability->colorOptionsForProduct($product))
            ->sortByDesc(fn (array $option): int => strlen((string) ($option['color_label'] ?? $option['color'] ?? '')))
            ->first(function (array $option) use ($normalizedMessage): bool {
                $colorLabel = $this->normalizeColorMatchText((string) ($option['color_label'] ?? $option['color'] ?? ''));

                return $colorLabel !== '' && str_contains($normalizedMessage, $colorLabel);
            });
    }

    private function normalizeColorMatchText(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/i', ' ')
            ->squish()
            ->value();
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
