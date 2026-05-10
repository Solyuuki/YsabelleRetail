<?php

namespace App\Services\Storefront\Assistant;

use Illuminate\Support\Str;

class StorefrontAssistantMessageNormalizer
{
    public function normalize(string $message, array $assistantContext = []): array
    {
        $trimmed = trim($message);
        $normalized = Str::lower($trimmed);
        $normalized = preg_replace('/[^\pL\pN\s-]+/u', ' ', $normalized) ?? $normalized;
        $normalized = str_replace(['sign-in', 'log-in'], ['sign in', 'log in'], $normalized);
        $normalized = $this->replaceAliases($normalized, $assistantContext);
        $normalized = trim((string) preg_replace('/\s+/u', ' ', $normalized));

        $rawNormalized = trim((string) preg_replace('/\s+/u', ' ', Str::lower($trimmed)));

        return [
            'original' => $trimmed,
            'normalized' => $normalized,
            'has_auth_signal' => $this->containsAny($normalized, ['login', 'signup', 'phone otp', 'email magic link']),
            'has_quick_signal' => $this->containsAny($normalized, ['quick', 'quick option']),
            'has_location_signal' => $this->containsAny($normalized, ['location', 'where']),
            'has_options_signal' => $this->containsAny($normalized, ['option', 'options']),
            'has_phone_auth_signal' => str_contains($normalized, 'phone otp'),
            'has_magic_link_signal' => str_contains($normalized, 'email magic link'),
            'has_support_signal' => $this->containsAny($normalized, [
                'checkout',
                'contact',
                'delivery',
                'how',
                'image search',
                'location',
                'login',
                'returns',
                'shipping',
                'signup',
                'size guide',
                'support',
                'visual search',
                'where',
            ]) || $this->containsAny($rawNormalized, [
                'account',
                'call',
                'email link',
                'login',
                'magic link',
                'mobile number',
                'otp',
                'phone',
                'register',
                'sign',
            ]),
        ];
    }

    private function replaceAliases(string $message, array $assistantContext): string
    {
        $patterns = [
            '/\b(saan|san)\b/u' => ' where location ',
            '/\b(paano|pano)\b/u' => ' how ',
            '/\b(puwede|pwede)\b/u' => ' available allowed ',
            '/\b(mabilis|quick|fast)\b/u' => ' quick option ',
            '/\b(sign[\s-]*up|signup|register|create account|account)\b/u' => ' signup ',
            '/\b(log\s*in|login|sign[\s-]*in|signin|sign)\b/u' => ' login ',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $message = preg_replace($pattern, $replacement, $message) ?? $message;
        }

        if ($this->isAuthContext($assistantContext) || $this->containsAny($message, ['login', 'signup'])) {
            $message = preg_replace('/\b(otp|code|phone|mobile number)\b/u', ' phone otp ', $message) ?? $message;
            $message = preg_replace('/\b(email link|magic link)\b/u', ' email magic link ', $message) ?? $message;
        }

        return $message;
    }

    private function isAuthContext(array $assistantContext): bool
    {
        $lastIntent = (string) ($assistantContext['last_intent'] ?? '');
        $lastTopic = (string) ($assistantContext['last_topic'] ?? '');

        return in_array($lastIntent, [
            StorefrontAssistantIntentRouter::INTENT_SUPPORT_LOGIN,
            StorefrontAssistantIntentRouter::INTENT_SUPPORT_SIGNUP,
            StorefrontAssistantIntentRouter::INTENT_SUPPORT_AUTH_QUICK_OPTIONS,
            StorefrontAssistantIntentRouter::INTENT_SUPPORT_AUTH_PHONE_OPTION_STATUS,
            StorefrontAssistantIntentRouter::INTENT_SUPPORT_AUTH_MAGIC_LINK_STATUS,
        ], true) || in_array($lastTopic, ['login', 'signup'], true);
    }

    private function containsAny(string $message, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }
}
