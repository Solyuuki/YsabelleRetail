<?php

namespace App\Services\Storefront\Assistant;

use Illuminate\Support\Arr;

class StorefrontAssistantContextResolver
{
    private const ALLOWED_ACTION_LABELS = [
        'Black sneakers',
        'Browse Products',
        'Browse all shoes',
        'Check my cart',
        'Checkout',
        'Contact Support',
        'Create Account',
        'Find running shoes',
        'Find similar by image',
        'Go to Checkout',
        'Login',
        'Open Cart',
        'Open Size Guide',
        'Open Visual Search',
        'Open catalog',
        'Open full catalog',
        'Returns Info',
        'Shipping Info',
        'Shoes under PHP 3,000',
        'Show my cart',
        'Start Image Search',
        'View cart',
    ];

    private const ALLOWED_ANSWER_TYPES = [
        'cart_summary',
        'clarification',
        'conversation',
        'guidance_answer',
        'out_of_scope',
        'product_results',
        'support_answer',
        'visual_search_answer',
    ];

    private const ALLOWED_DOMAINS = [
        'cart',
        'conversation',
        'general',
        'guidance',
        'product',
        'support',
        'visual_search',
    ];

    private const ALLOWED_TOPICS = [
        'authenticity',
        'care',
        'contact',
        'image_search',
        'location',
        'login',
        'ordering_flow',
        'returns',
        'shipping',
        'signup',
        'site_use',
        'size_guide',
    ];

    public function sanitize(mixed $input): array
    {
        if (! is_array($input)) {
            return [];
        }

        $intent = $this->sanitizeIntent($input['last_intent'] ?? null);
        $topic = $this->sanitizeTopic($input['last_topic'] ?? null);
        $domain = $this->sanitizeDomain($input['last_domain'] ?? null);
        $answerType = $this->sanitizeAnswerType($input['last_answer_type'] ?? null);
        $turnCount = $this->sanitizeTurnCount($input['turn_count_in_domain'] ?? null);
        $actions = $this->sanitizeActionLabels($input['last_actions'] ?? []);

        return array_filter([
            'last_intent' => $intent,
            'last_topic' => $topic,
            'last_domain' => $domain,
            'last_actions' => $actions,
            'last_answer_type' => $answerType,
            'turn_count_in_domain' => $turnCount,
        ], fn (mixed $value): bool => ! in_array($value, [null, []], true));
    }

    public function recoverIntent(array $explicitIntent, array $message, array $assistantContext): ?array
    {
        if (! in_array($explicitIntent['intent'] ?? null, [
            StorefrontAssistantIntentRouter::INTENT_FALLBACK,
            StorefrontAssistantIntentRouter::INTENT_OUT_OF_SCOPE,
        ], true)) {
            return null;
        }

        if ($this->isAuthContext($assistantContext)) {
            if ($message['has_phone_auth_signal']) {
                return [
                    'intent' => StorefrontAssistantIntentRouter::INTENT_SUPPORT_AUTH_PHONE_OPTION_STATUS,
                    'topic' => 'login',
                ];
            }

            if ($message['has_magic_link_signal']) {
                return [
                    'intent' => StorefrontAssistantIntentRouter::INTENT_SUPPORT_AUTH_MAGIC_LINK_STATUS,
                    'topic' => 'login',
                ];
            }

            if ($message['has_quick_signal'] || $message['has_auth_signal']) {
                return [
                    'intent' => StorefrontAssistantIntentRouter::INTENT_SUPPORT_AUTH_QUICK_OPTIONS,
                    'topic' => 'login',
                ];
            }
        }

        if ($message['has_options_signal'] && $this->isSupportLikeContext($assistantContext)) {
            return [
                'intent' => StorefrontAssistantIntentRouter::INTENT_SUPPORT_TOPIC_OPTIONS,
                'topic' => $assistantContext['last_topic'] ?? null,
            ];
        }

        if ($message['has_location_signal'] && $this->isLocationContext($assistantContext)) {
            return [
                'intent' => StorefrontAssistantIntentRouter::INTENT_SUPPORT_LOCATION,
                'topic' => 'location',
            ];
        }

        return null;
    }

    public function shouldUseSupportClarifier(array $message, array $assistantContext): bool
    {
        return $message['has_support_signal']
            || ($assistantContext !== [] && $this->isSupportLikeContext($assistantContext));
    }

    public function buildResponseContext(array $intent, array $response, array $previousContext): array
    {
        $effectiveIntent = $this->contextIntent($intent, $previousContext);
        $domain = $this->domainForIntent($effectiveIntent);
        $answerType = $this->answerTypeForIntent($effectiveIntent);
        $turnCount = $domain !== null && $domain === ($previousContext['last_domain'] ?? null)
            ? min(((int) ($previousContext['turn_count_in_domain'] ?? 0)) + 1, 12)
            : ($domain === null ? null : 1);

        return array_filter([
            'last_intent' => $effectiveIntent,
            'last_topic' => $this->topicForIntent($intent, $previousContext),
            'last_domain' => $domain,
            'last_actions' => $this->sanitizeOutgoingActions($response['actions'] ?? []),
            'last_answer_type' => $answerType,
            'turn_count_in_domain' => $turnCount,
        ], fn (mixed $value): bool => ! in_array($value, [null, []], true));
    }

    private function sanitizeIntent(mixed $value): ?string
    {
        $intent = is_string($value) ? trim($value) : '';

        return in_array($intent, [
            StorefrontAssistantIntentRouter::INTENT_GREETING,
            StorefrontAssistantIntentRouter::INTENT_SMALL_TALK,
            StorefrontAssistantIntentRouter::INTENT_CART,
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
            StorefrontAssistantIntentRouter::INTENT_GUIDANCE_ORDERING_FLOW,
            StorefrontAssistantIntentRouter::INTENT_VISUAL_SEARCH,
            StorefrontAssistantIntentRouter::INTENT_PRODUCT_SEARCH,
            StorefrontAssistantIntentRouter::INTENT_OUT_OF_SCOPE,
            StorefrontAssistantIntentRouter::INTENT_FALLBACK,
        ], true) ? $intent : null;
    }

    private function sanitizeTopic(mixed $value): ?string
    {
        $topic = is_string($value) ? trim($value) : '';

        return in_array($topic, self::ALLOWED_TOPICS, true) ? $topic : null;
    }

    private function sanitizeDomain(mixed $value): ?string
    {
        $domain = is_string($value) ? trim($value) : '';

        return in_array($domain, self::ALLOWED_DOMAINS, true) ? $domain : null;
    }

    private function sanitizeAnswerType(mixed $value): ?string
    {
        $answerType = is_string($value) ? trim($value) : '';

        return in_array($answerType, self::ALLOWED_ANSWER_TYPES, true) ? $answerType : null;
    }

    private function sanitizeTurnCount(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        return max(1, min((int) $value, 12));
    }

    private function sanitizeActionLabels(mixed $actions): array
    {
        if (! is_array($actions)) {
            return [];
        }

        return collect($actions)
            ->filter(fn (mixed $action): bool => is_string($action))
            ->map(fn (string $action): string => trim($action))
            ->filter(fn (string $action): bool => in_array($action, self::ALLOWED_ACTION_LABELS, true))
            ->take(5)
            ->values()
            ->all();
    }

    private function sanitizeOutgoingActions(array $actions): array
    {
        return collect($actions)
            ->map(fn (array $action): string => trim((string) Arr::get($action, 'label', '')))
            ->filter()
            ->take(5)
            ->values()
            ->all();
    }

    private function isAuthContext(array $assistantContext): bool
    {
        return in_array($assistantContext['last_intent'] ?? null, [
            StorefrontAssistantIntentRouter::INTENT_SUPPORT_LOGIN,
            StorefrontAssistantIntentRouter::INTENT_SUPPORT_SIGNUP,
            StorefrontAssistantIntentRouter::INTENT_SUPPORT_AUTH_QUICK_OPTIONS,
            StorefrontAssistantIntentRouter::INTENT_SUPPORT_AUTH_PHONE_OPTION_STATUS,
            StorefrontAssistantIntentRouter::INTENT_SUPPORT_AUTH_MAGIC_LINK_STATUS,
        ], true) || in_array($assistantContext['last_topic'] ?? null, ['login', 'signup'], true);
    }

    private function isLocationContext(array $assistantContext): bool
    {
        return in_array($assistantContext['last_intent'] ?? null, [
            StorefrontAssistantIntentRouter::INTENT_SUPPORT_CONTACT,
            StorefrontAssistantIntentRouter::INTENT_SUPPORT_LOCATION,
        ], true) || in_array($assistantContext['last_topic'] ?? null, ['contact', 'location'], true);
    }

    private function isSupportLikeContext(array $assistantContext): bool
    {
        return in_array($assistantContext['last_domain'] ?? null, ['support', 'guidance'], true);
    }

    private function contextIntent(array $intent, array $previousContext): string
    {
        if (($intent['intent'] ?? null) === StorefrontAssistantIntentRouter::INTENT_SUPPORT_TOPIC_OPTIONS) {
            return (string) ($previousContext['last_intent'] ?? StorefrontAssistantIntentRouter::INTENT_SUPPORT_TOPIC_OPTIONS);
        }

        return (string) $intent['intent'];
    }

    private function topicForIntent(array $intent, array $previousContext): ?string
    {
        return match ($intent['intent']) {
            StorefrontAssistantIntentRouter::INTENT_SUPPORT_LOGIN,
            StorefrontAssistantIntentRouter::INTENT_SUPPORT_SIGNUP,
            StorefrontAssistantIntentRouter::INTENT_SUPPORT_AUTH_QUICK_OPTIONS,
            StorefrontAssistantIntentRouter::INTENT_SUPPORT_AUTH_PHONE_OPTION_STATUS,
            StorefrontAssistantIntentRouter::INTENT_SUPPORT_AUTH_MAGIC_LINK_STATUS => 'login',
            StorefrontAssistantIntentRouter::INTENT_SUPPORT_SHIPPING => 'shipping',
            StorefrontAssistantIntentRouter::INTENT_SUPPORT_RETURNS => 'returns',
            StorefrontAssistantIntentRouter::INTENT_SUPPORT_CONTACT => 'contact',
            StorefrontAssistantIntentRouter::INTENT_SUPPORT_SIZE_GUIDE => 'size_guide',
            StorefrontAssistantIntentRouter::INTENT_SUPPORT_LOCATION => 'location',
            StorefrontAssistantIntentRouter::INTENT_GUIDANCE_SITE_USE => 'site_use',
            StorefrontAssistantIntentRouter::INTENT_GUIDANCE_IMAGE_SEARCH => 'image_search',
            StorefrontAssistantIntentRouter::INTENT_GUIDANCE_ORDERING_FLOW => 'ordering_flow',
            StorefrontAssistantIntentRouter::INTENT_SUPPORT_TOPIC_OPTIONS => $this->sanitizeTopic(
                $previousContext['last_topic'] ?? $intent['topic'] ?? null
            ),
            default => $this->sanitizeTopic($intent['topic'] ?? null),
        };
    }

    private function domainForIntent(string $intent): ?string
    {
        return match (true) {
            in_array($intent, [
                StorefrontAssistantIntentRouter::INTENT_GREETING,
                StorefrontAssistantIntentRouter::INTENT_SMALL_TALK,
            ], true) => 'conversation',
            $intent === StorefrontAssistantIntentRouter::INTENT_CART => 'cart',
            $intent === StorefrontAssistantIntentRouter::INTENT_PRODUCT_SEARCH => 'product',
            $intent === StorefrontAssistantIntentRouter::INTENT_VISUAL_SEARCH => 'visual_search',
            str_starts_with($intent, 'support.') || $intent === StorefrontAssistantIntentRouter::INTENT_SUPPORT || $intent === StorefrontAssistantIntentRouter::INTENT_CHECKOUT => 'support',
            str_starts_with($intent, 'guidance.') => 'guidance',
            in_array($intent, [
                StorefrontAssistantIntentRouter::INTENT_FALLBACK,
                StorefrontAssistantIntentRouter::INTENT_OUT_OF_SCOPE,
            ], true) => 'general',
            default => null,
        };
    }

    private function answerTypeForIntent(string $intent): ?string
    {
        return match (true) {
            in_array($intent, [
                StorefrontAssistantIntentRouter::INTENT_GREETING,
                StorefrontAssistantIntentRouter::INTENT_SMALL_TALK,
            ], true) => 'conversation',
            $intent === StorefrontAssistantIntentRouter::INTENT_CART => 'cart_summary',
            $intent === StorefrontAssistantIntentRouter::INTENT_PRODUCT_SEARCH => 'product_results',
            $intent === StorefrontAssistantIntentRouter::INTENT_VISUAL_SEARCH => 'visual_search_answer',
            str_starts_with($intent, 'support.') || $intent === StorefrontAssistantIntentRouter::INTENT_SUPPORT || $intent === StorefrontAssistantIntentRouter::INTENT_CHECKOUT => 'support_answer',
            str_starts_with($intent, 'guidance.') => 'guidance_answer',
            $intent === StorefrontAssistantIntentRouter::INTENT_OUT_OF_SCOPE => 'out_of_scope',
            $intent === StorefrontAssistantIntentRouter::INTENT_FALLBACK => 'clarification',
            default => null,
        };
    }
}
