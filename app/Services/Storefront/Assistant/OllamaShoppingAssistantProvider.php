<?php

namespace App\Services\Storefront\Assistant;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class OllamaShoppingAssistantProvider implements ShoppingAssistantGuidanceProvider
{
    private const SUPPORTED_INTENTS = [
        'greeting',
        'small_talk',
        'ecommerce_cart',
        'ecommerce_checkout',
        'ecommerce_support',
        'visual_search',
        'ecommerce_product_search',
    ];

    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('storefront.assistant.ai.enabled', false)
            && config('storefront.assistant.ai.provider') === 'ollama'
            && filled(config('storefront.assistant.ai.ollama.model'));
    }

    public function complete(string $intent, string $userMessage, array $response, array $context): ?string
    {
        if (! $this->isEnabled() || ! in_array($intent, self::SUPPORTED_INTENTS, true)) {
            return null;
        }

        try {
            $httpResponse = $this->http
                ->acceptJson()
                ->timeout((int) config('storefront.assistant.ai.ollama.timeout', 8))
                ->post($this->endpoint('/api/generate'), [
                    'model' => config('storefront.assistant.ai.ollama.model'),
                    'stream' => false,
                    'options' => [
                        'temperature' => (float) config('storefront.assistant.ai.ollama.temperature', 0.2),
                        'num_predict' => (int) config('storefront.assistant.ai.ollama.max_tokens', 96),
                    ],
                    'system' => $this->systemPrompt(),
                    'prompt' => $this->promptFor($intent, $userMessage, $response, $context),
                ]);
        } catch (\Throwable) {
            return null;
        }

        if (! $httpResponse->successful()) {
            return null;
        }

        return $this->sanitizeAnswer(
            $intent,
            $httpResponse->json('response'),
            $response['answer'] ?? '',
        );
    }

    public function health(): array
    {
        if (! $this->isEnabled()) {
            return [
                'configured' => false,
                'reachable' => false,
                'model' => (string) config('storefront.assistant.ai.ollama.model', ''),
                'model_available' => false,
                'message' => 'Ollama guidance is disabled.',
            ];
        }

        try {
            $response = $this->http
                ->acceptJson()
                ->timeout((int) config('storefront.assistant.ai.ollama.timeout', 8))
                ->get($this->endpoint('/api/tags'));
        } catch (\Throwable $exception) {
            return [
                'configured' => true,
                'reachable' => false,
                'model' => (string) config('storefront.assistant.ai.ollama.model'),
                'model_available' => false,
                'message' => $exception->getMessage(),
            ];
        }

        if (! $response->successful()) {
            return [
                'configured' => true,
                'reachable' => false,
                'model' => (string) config('storefront.assistant.ai.ollama.model'),
                'model_available' => false,
                'message' => 'Ollama returned HTTP '.$response->status().'.',
            ];
        }

        $model = (string) config('storefront.assistant.ai.ollama.model');
        $availableModels = collect($response->json('models', []))
            ->map(fn ($entry): string => (string) ($entry['name'] ?? ''))
            ->filter()
            ->values();

        $modelAvailable = $availableModels->contains(
            fn (string $availableModel): bool => $availableModel === $model || str_starts_with($availableModel, $model.':'),
        );

        return [
            'configured' => true,
            'reachable' => true,
            'model' => $model,
            'model_available' => $modelAvailable,
            'message' => $modelAvailable
                ? 'Ollama is reachable and the configured model is available.'
                : 'Ollama is reachable, but the configured model was not listed by /api/tags.',
        ];
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('storefront.assistant.ai.ollama.base_url', 'http://127.0.0.1:11434'), '/').$path;
    }

    private function systemPrompt(): string
    {
        return implode("\n", [
            'You are the Ysabelle Retail Smart Shopping Assistant.',
            'You must stay strictly within Ysabelle Retail shopping support.',
            'Use only the supplied context and approved response data.',
            'Do not answer general knowledge, history, politics, science, coding, or unrelated questions.',
            'Do not invent products, prices, stock, policies, shipping rules, checkout steps, or brand claims.',
            'Keep the reply short, polished, premium, and under three sentences.',
            'When useful, you may add one short follow-up question about size, color, budget, or intended use.',
            'Return plain text only.',
        ]);
    }

    private function promptFor(string $intent, string $userMessage, array $response, array $context): string
    {
        $payload = [
            'intent' => $intent,
            'user_message' => $userMessage,
            'approved_answer' => $response['answer'] ?? '',
            'actions' => Arr::pluck($response['actions'] ?? [], 'label'),
            'products' => collect($context['products'] ?? [])
                ->map(fn (array $product): array => Arr::only($product, ['name', 'category', 'price_label', 'availability']))
                ->values()
                ->all(),
            'criteria' => $context['criteria'] ?? [],
        ];

        return implode("\n\n", [
            'Rewrite the approved answer so it sounds polished, helpful, and conversational, but keep the meaning and scope unchanged.',
            'If the approved answer is already clear, keep it close.',
            'Never add facts that are not in the approved response or context.',
            'If products are present, keep the answer grounded in those products only.',
            'Context:',
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}',
        ]);
    }

    private function sanitizeAnswer(string $intent, mixed $candidate, string $fallback): ?string
    {
        if (! is_string($candidate)) {
            return null;
        }

        $answer = trim($candidate);

        if ($answer === '') {
            return null;
        }

        if (str_starts_with($answer, '{')) {
            $decoded = json_decode($answer, true);

            if (is_array($decoded) && is_string($decoded['answer'] ?? null)) {
                $answer = trim($decoded['answer']);
            }
        }

        $answer = preg_replace('/\s+/u', ' ', $answer) ?? $answer;
        $answer = trim($answer);

        if ($answer === '') {
            return null;
        }

        if (! $this->looksDomainBounded($intent, $answer, $fallback)) {
            return null;
        }

        return Str::limit($answer, 280, '');
    }

    private function looksDomainBounded(string $intent, string $answer, string $fallback): bool
    {
        if ($intent === 'greeting' || $intent === 'small_talk') {
            return $this->containsAssistantDomainLanguage($answer);
        }

        if ($intent === 'ecommerce_product_search' || $intent === 'visual_search') {
            return $this->containsAny($answer, ['catalog', 'product', 'products', 'shoe', 'shoes', 'match']);
        }

        if ($intent === 'ecommerce_cart' || $intent === 'ecommerce_checkout' || $intent === 'ecommerce_support') {
            return $this->containsAssistantDomainLanguage($answer);
        }

        return $this->containsAssistantDomainLanguage($answer) || $answer === $fallback;
    }

    private function containsAssistantDomainLanguage(string $answer): bool
    {
        return $this->containsAny($answer, [
            'Ysabelle',
            'catalog',
            'cart',
            'checkout',
            'product',
            'products',
            'shoe',
            'shoes',
            'stock',
            'store',
            'support',
        ]);
    }

    private function containsAny(string $message, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains(Str::lower($message), Str::lower($needle))) {
                return true;
            }
        }

        return false;
    }
}
