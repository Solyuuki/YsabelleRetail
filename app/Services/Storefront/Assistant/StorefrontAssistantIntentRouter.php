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

    public const INTENT_GUIDANCE_SITE_USE = 'guidance.site_use';

    public const INTENT_GUIDANCE_IMAGE_SEARCH = 'guidance.image_search';

    public const INTENT_GUIDANCE_ORDERING_FLOW = 'guidance.ordering_flow';

    public const INTENT_VISUAL_SEARCH = 'visual_search';

    public const INTENT_PRODUCT_SEARCH = 'ecommerce_product_search';

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

    public function classify(string $message, array $criteria): array
    {
        $normalized = Str::lower(trim($message));

        if ($this->isGreetingIntent($message, $normalized, $criteria)) {
            return ['intent' => self::INTENT_GREETING];
        }

        if ($this->isSmallTalkIntent($message, $normalized, $criteria)) {
            return ['intent' => self::INTENT_SMALL_TALK];
        }

        if ($this->isCartIntent($normalized)) {
            return ['intent' => self::INTENT_CART];
        }

        if ($intent = $this->supportIntent($normalized)) {
            return $intent;
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

        if ($this->hasHighConfidenceProductIntent($normalized, $criteria)) {
            return ['intent' => self::INTENT_PRODUCT_SEARCH];
        }

        if ($this->isOutOfScopeIntent($normalized, $criteria)) {
            return ['intent' => self::INTENT_OUT_OF_SCOPE];
        }

        return ['intent' => self::INTENT_FALLBACK];
    }

    private function supportIntent(string $message): ?array
    {
        return match (true) {
            $this->containsAny($message, ['log in', 'login', 'sign in', 'signin']) => ['intent' => self::INTENT_SUPPORT_LOGIN],
            $this->containsAny($message, ['sign up', 'signup', 'register', 'create account']) => ['intent' => self::INTENT_SUPPORT_SIGNUP],
            $this->containsAny($message, ['shipping', 'delivery']) => ['intent' => self::INTENT_SUPPORT_SHIPPING],
            $this->containsAny($message, ['return', 'refund', 'exchange']) => ['intent' => self::INTENT_SUPPORT_RETURNS],
            $this->containsAny($message, ['size guide', 'sizing', 'true to size', 'fit', 'size']) => ['intent' => self::INTENT_SUPPORT_SIZE_GUIDE],
            $this->containsAny($message, ['contact', 'email', 'phone', 'call', 'reach you', 'reach support']) => ['intent' => self::INTENT_SUPPORT_CONTACT],
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

        return in_array($this->simplifyMessage($message), self::SMALL_TALK_PHRASES, true);
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
            || $this->supportIntent($message) !== null
            || $this->guidanceIntent($message) !== null;
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
}
