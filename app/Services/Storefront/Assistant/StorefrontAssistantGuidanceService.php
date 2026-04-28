<?php

namespace App\Services\Storefront\Assistant;

class StorefrontAssistantGuidanceService
{
    public function __construct(
        private readonly OllamaShoppingAssistantProvider $ollama,
    ) {}

    public function complete(string $intent, string $userMessage, array $response, array $context): array
    {
        $response = $this->normalizeResponse($response);

        $answer = $this->ollama->complete($intent, $userMessage, $response, $context);

        if (is_string($answer) && $answer !== '') {
            $response['answer'] = $answer;
        }

        return $response;
    }

    public function stream(string $intent, string $userMessage, array $response, array $context): \Generator
    {
        $resolved = $this->complete($intent, $userMessage, $response, $context);

        foreach ($this->chunkAnswer($resolved['answer']) as $chunk) {
            yield [
                'event' => 'chunk',
                'data' => ['text' => $chunk],
            ];
        }

        yield [
            'event' => 'done',
            'data' => $resolved,
        ];
    }

    public function health(): array
    {
        return $this->ollama->health();
    }

    private function normalizeResponse(array $response): array
    {
        return [
            'answer' => (string) ($response['answer'] ?? ''),
            'products' => array_values($response['products'] ?? []),
            'actions' => array_values($response['actions'] ?? []),
        ];
    }

    private function chunkAnswer(string $answer): array
    {
        $tokens = preg_split('/(\s+)/u', trim($answer), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];

        if ($tokens === []) {
            return [];
        }

        $chunks = [];
        $buffer = '';
        $wordCount = 0;

        foreach ($tokens as $token) {
            $buffer .= $token;

            if (! preg_match('/^\s+$/u', $token)) {
                $wordCount++;
            }

            if ($wordCount >= 4 || preg_match('/[.!?]\s*$/u', $buffer)) {
                $chunks[] = $buffer;
                $buffer = '';
                $wordCount = 0;
            }
        }

        if ($buffer !== '') {
            $chunks[] = $buffer;
        }

        return $chunks;
    }
}
