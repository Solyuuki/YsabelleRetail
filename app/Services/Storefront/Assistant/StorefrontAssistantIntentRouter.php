<?php

namespace App\Services\Storefront\Assistant;

use Illuminate\Support\Str;

class StorefrontAssistantIntentRouter
{
    public const INTENT_GREETING = 'greeting';

    public const INTENT_SMALL_TALK = 'small_talk';

    public const INTENT_CART = 'ecommerce_cart';

    public const INTENT_CHECKOUT = 'ecommerce_checkout';

    public const INTENT_SUPPORT = 'ecommerce_support';

    public const INTENT_SUPPORT_SHIPPING = 'support.shipping';

    public const INTENT_SUPPORT_RETURNS = 'support.returns';

    public const INTENT_SUPPORT_CONTACT = 'support.contact';

    public const INTENT_SUPPORT_SIZE_GUIDE = 'support.size_guide';

    public const INTENT_SUPPORT_LOCATION = 'support.location';

    public const INTENT_SUPPORT_LOGIN = 'support.login';

    public const INTENT_SUPPORT_SIGNUP = 'support.signup';

    public const INTENT_SUPPORT_AUTH_QUICK_OPTIONS = 'support.auth_quick_options';

    public const INTENT_SUPPORT_AUTH_PHONE_OPTION_STATUS = 'support.auth_phone_option_status';

    public const INTENT_SUPPORT_AUTH_MAGIC_LINK_STATUS = 'support.auth_magic_link_status';

    public const INTENT_SUPPORT_TOPIC_OPTIONS = 'support.topic_options';

    public const INTENT_GUIDANCE_SITE_USE = 'guidance.site_use';

    public const INTENT_GUIDANCE_IMAGE_SEARCH = 'guidance.image_search';

    public const INTENT_GUIDANCE_ORDERING_FLOW = 'guidance.ordering_flow';

    public const INTENT_VISUAL_SEARCH = 'visual_search';

    public const INTENT_PRODUCT_SEARCH = 'ecommerce_product_search';

    public const INTENT_PRODUCT_PRICE_RANKING = 'ecommerce_product_price_ranking';

    public const INTENT_OUT_OF_SCOPE = 'out_of_scope';

    public const INTENT_FALLBACK = 'fallback';

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

    public function classify(string $message, array $criteria, array $commerce = []): array
    {
        $normalized = Str::lower(trim($message));

        if ($this->isCartIntent($normalized) || (($commerce['intent'] ?? null) === 'cart')) {
            return ['intent' => self::INTENT_CART];
        }

        if ($intent = $this->supportIntent($normalized, $criteria, $commerce)) {
            return $intent;
        }

        if ($this->isGreetingIntent($message, $normalized, $criteria, $commerce)) {
            return ['intent' => self::INTENT_GREETING];
        }

        if ($this->isSmallTalkIntent($message, $normalized, $criteria, $commerce)) {
            return ['intent' => self::INTENT_SMALL_TALK];
        }

        if ($this->isOutOfScopeIntent($normalized, $criteria, $commerce)) {
            return ['intent' => self::INTENT_OUT_OF_SCOPE];
        }

        if ($intent = $this->guidanceIntent($normalized)) {
            return $intent;
        }

        if ($this->isCheckoutIntent($normalized)) {
            return ['intent' => self::INTENT_CHECKOUT];
        }

        if ($this->isVisualSearchIntent($normalized)) {
            return ['intent' => self::INTENT_VISUAL_SEARCH];
        }

        if ($this->isMostExpensiveIntent($normalized)) {
            return ['intent' => self::INTENT_PRODUCT_PRICE_RANKING];
        }

        if ($this->hasHighConfidenceProductIntent($normalized, $criteria, $commerce)) {
            return ['intent' => self::INTENT_PRODUCT_SEARCH];
        }

        return ['intent' => self::INTENT_FALLBACK];
    }

    private function supportIntent(string $message, array $criteria, array $commerce): ?array
    {
        return match (true) {
            $this->isQuickAuthIntent($message) => ['intent' => self::INTENT_SUPPORT_AUTH_QUICK_OPTIONS],
            $this->isPhoneAuthIntent($message) => ['intent' => self::INTENT_SUPPORT_AUTH_PHONE_OPTION_STATUS],
            $this->isMagicLinkIntent($message) => ['intent' => self::INTENT_SUPPORT_AUTH_MAGIC_LINK_STATUS],
            $this->containsAny($message, ['log in', 'login', 'sign in', 'signin']) => ['intent' => self::INTENT_SUPPORT_LOGIN],
            $this->containsAny($message, ['sign up', 'signup', 'register', 'create account']) => ['intent' => self::INTENT_SUPPORT_SIGNUP],
            $this->containsAny($message, ['shipping', 'delivery']) => ['intent' => self::INTENT_SUPPORT_SHIPPING],
            $this->containsAny($message, ['return', 'refund', 'exchange']) => ['intent' => self::INTENT_SUPPORT_RETURNS],
            $this->containsAny($message, ['size guide', 'sizing', 'true to size', 'fit guide', 'size chart']) => ['intent' => self::INTENT_SUPPORT_SIZE_GUIDE],
            $this->containsAny($message, ['fit']) && ! $this->hasCommerceSizeSignal($criteria, $commerce) => ['intent' => self::INTENT_SUPPORT_SIZE_GUIDE],
            $this->containsAny($message, ['size']) && ! $this->hasCommerceSizeSignal($criteria, $commerce) => ['intent' => self::INTENT_SUPPORT_SIZE_GUIDE],
            $this->containsAny($message, ['contact', 'support email', 'phone number', 'call', 'reach you', 'reach support']) => ['intent' => self::INTENT_SUPPORT_CONTACT],
            $this->containsAny($message, ['where is', 'where are', 'located', 'location', 'address', 'branch', 'branches']) => ['intent' => self::INTENT_SUPPORT_LOCATION],
            $this->containsAny($message, ['authentic', 'genuine']) => ['intent' => self::INTENT_SUPPORT, 'topic' => 'authenticity'],
            $this->containsAny($message, ['care', 'policy', 'support']) => ['intent' => self::INTENT_SUPPORT, 'topic' => 'care'],
            default => null,
        };
    }

    private function guidanceIntent(string $message): ?array
    {
        return match (true) {
            $this->containsAny($message, ['how to order', 'how do i order', 'how can i order', 'how to buy', 'how do i buy', 'how to place an order', 'ordering flow', 'order steps']) => ['intent' => self::INTENT_GUIDANCE_ORDERING_FLOW],
            $this->containsAny($message, ['how to use image search', 'how do i use image search', 'how to use visual search', 'how do i use visual search', 'image search help', 'visual search help']) => ['intent' => self::INTENT_GUIDANCE_IMAGE_SEARCH],
            $this->containsAny($message, ['how to use this website', 'how do i use this website', 'how to use this site', 'how do i use this site', 'how does this website work', 'user manual', 'guided tour', 'website help', 'site help']) => ['intent' => self::INTENT_GUIDANCE_SITE_USE],
            default => null,
        };
    }

    private function isGreetingIntent(string $message, string $normalized, array $criteria, array $commerce): bool
    {
        $simplified = $this->simplifyMessage($message);

        if (in_array($simplified, self::GREETING_PHRASES, true)) {
            return true;
        }

        return $this->startsWithAny($simplified, ['hello ', 'hey ', 'hi ']) && $this->tokenCount($simplified) <= 3;
    }

    private function isSmallTalkIntent(string $message, string $normalized, array $criteria, array $commerce): bool
    {
        return in_array($this->simplifyMessage($message), self::SMALL_TALK_PHRASES, true);
    }

    private function hasHighConfidenceProductIntent(string $message, array $criteria, array $commerce): bool
    {
        if (($commerce['flags']['has_low_signal_text'] ?? false) === true && ($commerce['flags']['has_product_signal'] ?? false) !== true) {
            return false;
        }

        if (
            $this->isAvailabilityIntent($message)
            && (
                ($commerce['flags']['stock_intent'] ?? false) === true
                || ($commerce['flags']['references_current_product'] ?? false) === true
                || filled($commerce['entities']['product_name'] ?? null)
                || filled($commerce['entities']['size'] ?? null)
                || $this->hasStructuredProductSignal($criteria)
            )
        ) {
            return true;
        }

        if (in_array($commerce['intent'] ?? null, [
            'budget_search',
            'current_product',
            'product_availability',
            'product_exact_lookup',
            'product_search',
            'size_stock',
        ], true)) {
            return true;
        }

        return $this->hasStructuredProductSignal($criteria)
            || $this->hasProductKeywordMatch($message)
            || $this->hasDirectProductLookupSignal($message, $criteria, $commerce);
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

    private function hasDirectProductLookupSignal(string $message, array $criteria, array $commerce): bool
    {
        if (($commerce['flags']['explicit_lookup'] ?? false) && filled($commerce['entities']['product_name'] ?? null)) {
            return true;
        }

        if (($commerce['flags']['references_current_product'] ?? false) === true) {
            return true;
        }

        if (! $this->containsAny($message, [
            'can you find',
            'could you find',
            'do you have',
            'find me',
            'find this',
            'show me',
            'show this',
            'find ',
            'show ',
            'this item',
            'this pair',
            'this product',
            'this shoe',
        ])) {
            return false;
        }

        return count($criteria['keywords'] ?? []) >= 2 || $this->containsAny($message, [
            'this item',
            'this pair',
            'this product',
            'this shoe',
        ]);
    }

    private function isOutOfScopeIntent(string $message, array $criteria, array $commerce): bool
    {
        if (($commerce['flags']['has_product_signal'] ?? false) === true) {
            return false;
        }

        if ($this->hasDomainSignal($message, $criteria, $commerce)) {
            return false;
        }

        if ($this->containsAny($message, self::OUT_OF_SCOPE_KEYWORDS)) {
            return true;
        }

        return (bool) preg_match('/^(what|who|when|where|why|how|tell|explain|solve|write)\b/i', $message);
    }

    private function hasDomainSignal(string $message, array $criteria, array $commerce): bool
    {
        return $this->hasStructuredProductSignal($criteria)
            || (($commerce['flags']['has_product_signal'] ?? false) === true)
            || $this->hasProductKeywordMatch($message)
            || $this->isCartIntent($message)
            || $this->isCheckoutIntent($message)
            || $this->isVisualSearchIntent($message)
            || $this->isAvailabilityIntent($message)
            || $this->supportIntent($message, $criteria, $commerce) !== null
            || $this->guidanceIntent($message) !== null;
    }

    private function hasCommerceSizeSignal(array $criteria, array $commerce): bool
    {
        if (filled($criteria['size'] ?? null)) {
            return true;
        }

        if (filled($commerce['entities']['size'] ?? null)) {
            return true;
        }

        return in_array($commerce['intent'] ?? null, [
            'current_product',
            'product_availability',
            'product_exact_lookup',
            'product_search',
            'size_stock',
        ], true);
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
            || str_contains($message, 'complete order')
            || str_contains($message, 'buy now');
    }

    private function isAvailabilityIntent(string $message): bool
    {
        return $this->containsAny($message, self::AVAILABILITY_KEYWORDS);
    }

    private function isMostExpensiveIntent(string $message): bool
    {
        if ($this->containsAny($message, [
            'most expensive',
            'highest price',
            'highest priced',
            'top priced',
            'priciest',
            'pinaka mahal',
            'pinakamahal',
        ])) {
            return true;
        }

        if (
            ($this->containsAny($message, ['expensive', 'premium', 'luxury', 'mahal']) && $this->containsAny($message, ['highest', 'top', 'most', 'price', 'priced']))
            || ($this->containsAny($message, ['mahal']) && $this->containsAny($message, ['ano', 'dito', 'product', 'products', 'shoe', 'shoes', 'sneaker', 'sneakers']))
        ) {
            return true;
        }

        return preg_match('/\bmahal na (shoe|shoes|sapatos|sneaker|sneakers|product|products)\b/u', $message) === 1;
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

    private function isQuickAuthIntent(string $message): bool
    {
        return $this->containsAny($message, ['quick']) && $this->containsAny($message, ['login', 'signup']);
    }

    private function isPhoneAuthIntent(string $message): bool
    {
        return str_contains($message, 'phone otp') && $this->containsAny($message, ['login', 'signup']);
    }

    private function isMagicLinkIntent(string $message): bool
    {
        return str_contains($message, 'email magic link') && $this->containsAny($message, ['login', 'signup']);
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
}
