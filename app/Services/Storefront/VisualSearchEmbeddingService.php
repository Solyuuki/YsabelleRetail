<?php

namespace App\Services\Storefront;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class VisualSearchEmbeddingService
{
    public function health(): array
    {
        if (! $this->enabled()) {
            return [
                'configured' => false,
                'available' => false,
                'reachable' => false,
                'model' => $this->model(),
                'embedding_version' => $this->embeddingVersion(),
                'service' => 'python-cli',
                'message' => 'Embedding service is disabled.',
            ];
        }

        try {
            $payload = $this->runCommand(['health']);

            return [
                'configured' => true,
                'available' => (bool) ($payload['ok'] ?? false),
                'reachable' => (bool) ($payload['ok'] ?? false),
                'model' => $payload['model'] ?? $this->model(),
                'embedding_version' => $payload['embedding_version'] ?? $this->embeddingVersion(),
                'service' => $payload['service'] ?? 'python-cli',
                'device' => $payload['device'] ?? null,
                'message' => ($payload['ok'] ?? false)
                    ? 'Embedding service is ready.'
                    : (string) ($payload['error'] ?? 'Embedding service is unavailable.'),
            ];
        } catch (\Throwable $exception) {
            return [
                'configured' => true,
                'available' => false,
                'reachable' => false,
                'model' => $this->model(),
                'embedding_version' => $this->embeddingVersion(),
                'service' => 'python-cli',
                'message' => $exception->getMessage(),
            ];
        }
    }

    public function embedUpload(UploadedFile $image): ?array
    {
        $path = $image->getRealPath();

        if (! is_string($path) || $path === '' || ! is_file($path)) {
            return null;
        }

        $result = $this->embedPaths([
            [
                'id' => 'upload',
                'path' => $path,
            ],
        ]);

        return $result['upload'] ?? null;
    }

    public function embedPaths(array $items): array
    {
        if (! $this->enabled()) {
            return [];
        }

        $normalized = collect($items)
            ->map(function (array $item): ?array {
                $id = trim((string) ($item['id'] ?? ''));
                $path = trim((string) ($item['path'] ?? ''));

                if ($id === '' || $path === '' || ! is_file($path)) {
                    return null;
                }

                return [
                    'id' => $id,
                    'path' => $path,
                ];
            })
            ->filter()
            ->values()
            ->all();

        if ($normalized === []) {
            return [];
        }

        $payloadPath = null;

        try {
            $payloadPath = $this->writeBatchPayload($normalized);
            $payload = $this->runCommand(['embed-batch', '--input', $payloadPath], $this->timeoutSeconds());

            return collect(Arr::get($payload, 'results', []))
                ->filter(fn (mixed $result): bool => is_array($result) && isset($result['id']))
                ->mapWithKeys(function (array $result): array {
                    return [(string) $result['id'] => $result];
                })
                ->all();
        } finally {
            if (is_string($payloadPath) && is_file($payloadPath)) {
                @unlink($payloadPath);
            }
        }
    }

    public function enabled(): bool
    {
        return (bool) config('storefront.assistant.visual_search.embedding.enabled', true);
    }

    public function model(): string
    {
        return (string) config('storefront.assistant.visual_search.embedding.model', 'openai/clip-vit-base-patch32');
    }

    public function embeddingVersion(): string
    {
        return (string) config('storefront.assistant.visual_search.embedding.version', 'clip-b32-v1');
    }

    public function timeoutSeconds(): int
    {
        return max(10, (int) config('storefront.assistant.visual_search.embedding.timeout', 120));
    }

    private function writeBatchPayload(array $items): string
    {
        $directory = storage_path('app/visual-search');

        if (! is_dir($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $path = $directory.DIRECTORY_SEPARATOR.'embed-batch-'.Str::uuid().'.json';
        file_put_contents($path, json_encode(['items' => $items], JSON_THROW_ON_ERROR));

        return $path;
    }

    private function runCommand(array $arguments, ?int $timeout = null): array
    {
        $command = array_merge(
            [
                (string) config('storefront.assistant.visual_search.embedding.python_binary', 'python'),
                $this->scriptPath(),
                '--model',
                $this->model(),
                '--embedding-version',
                $this->embeddingVersion(),
            ],
            $arguments,
        );

        $process = new Process($command, base_path(), [
            'PYTHONIOENCODING' => 'utf-8',
            'VISUAL_SEARCH_EMBEDDING_MODEL' => $this->model(),
            'VISUAL_SEARCH_EMBEDDING_VERSION' => $this->embeddingVersion(),
        ]);
        $process->setTimeout($timeout ?? $this->timeoutSeconds());
        $process->run();

        if (! $process->isSuccessful()) {
            $errorOutput = trim($process->getErrorOutput());
            $output = trim($process->getOutput());
            $message = $errorOutput !== '' ? $errorOutput : $output;

            throw new \RuntimeException($message !== '' ? $message : 'Embedding service command failed.');
        }

        $payload = json_decode($process->getOutput(), true);

        if (! is_array($payload)) {
            throw new \RuntimeException('Embedding service returned an invalid payload.');
        }

        if (($payload['ok'] ?? false) !== true) {
            throw new \RuntimeException((string) ($payload['error'] ?? 'Embedding service reported a failure.'));
        }

        return $payload;
    }

    private function scriptPath(): string
    {
        return (string) config('storefront.assistant.visual_search.embedding.script', base_path('tools/visual_search_embedding_service.py'));
    }
}
