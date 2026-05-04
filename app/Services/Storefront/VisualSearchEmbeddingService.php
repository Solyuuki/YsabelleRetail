<?php

namespace App\Services\Storefront;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class VisualSearchEmbeddingService
{
    public function __construct(
        private readonly ImageFeatureExtractor $featureExtractor,
    ) {}

    public function health(): array
    {
        if ($this->shouldUseTestingFake()) {
            return [
                'configured' => true,
                'available' => true,
                'reachable' => true,
                'model' => $this->model(),
                'embedding_version' => $this->embeddingVersion(),
                'service' => 'php-testing-fake',
                'message' => 'Testing embedding service is ready.',
            ];
        }

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

        if ($this->shouldUseTestingFake()) {
            return collect($normalized)
                ->mapWithKeys(function (array $item): array {
                    return [$item['id'] => $this->fakeEmbeddingPayload($item['path'])];
                })
                ->all();
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

    private function shouldUseTestingFake(): bool
    {
        return app()->environment('testing')
            && (bool) config('storefront.assistant.visual_search.embedding.testing_fake', false);
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

    private function fakeEmbeddingPayload(string $path): array
    {
        $binary = file_get_contents($path);

        if (! is_string($binary) || $binary === '') {
            throw new \RuntimeException('Testing embedding service could not read the image payload.');
        }

        $features = $this->featureExtractor->extractFromBinary($binary);

        if (! is_array($features)) {
            throw new \RuntimeException('Testing embedding service could not extract image features.');
        }

        $embedding = $this->fakeEmbeddingVector($features);

        return [
            'ok' => true,
            'embedding' => $embedding,
            'crop_embeddings' => [
                'full' => $embedding,
                'center' => $embedding,
                'focus' => $embedding,
            ],
            'shoe_probability' => $this->fakeShoeProbability($features),
            'metadata' => [
                'format' => strtolower((string) pathinfo($path, PATHINFO_EXTENSION)),
                'source_mode' => 'gd-testing-fake',
                'original_width' => (int) ($features['width'] ?? 0),
                'original_height' => (int) ($features['height'] ?? 0),
                'width' => (int) ($features['width'] ?? 0),
                'height' => (int) ($features['height'] ?? 0),
                'normalized_width' => 32,
                'normalized_height' => 32,
                'had_alpha' => false,
                'trim_applied' => false,
                'crop_box' => null,
                'blur_score' => $this->fakeBlurScore($features),
            ],
        ];
    }

    private function fakeEmbeddingVector(array $features): array
    {
        $vector = array_merge(
            array_map(fn (mixed $value): float => round((float) $value, 8), $features['color_histogram'] ?? []),
            array_map(fn (mixed $value): float => round((float) $value, 8), $features['shape_profile_x'] ?? []),
            array_map(fn (mixed $value): float => round((float) $value, 8), $features['shape_profile_y'] ?? []),
            [
                round((float) ($features['mean_red'] ?? 0.0), 8),
                round((float) ($features['mean_green'] ?? 0.0), 8),
                round((float) ($features['mean_blue'] ?? 0.0), 8),
                round((float) ($features['edge_density'] ?? 0.0), 8),
                round((float) ($features['foreground_ratio'] ?? 0.0), 8),
                round(min(4.0, max(0.0, (float) ($features['aspect_ratio'] ?? 0.0))) / 4, 8),
            ],
        );

        $norm = sqrt(array_reduce($vector, fn (float $carry, float $value): float => $carry + ($value ** 2), 0.0));

        if ($norm <= 0.0) {
            return $vector;
        }

        return array_map(
            fn (float $value): float => round($value / $norm, 8),
            $vector,
        );
    }

    private function fakeShoeProbability(array $features): float
    {
        $shapeX = $features['shape_profile_x'] ?? [];
        $shapeY = $features['shape_profile_y'] ?? [];

        if (! is_array($shapeX) || ! is_array($shapeY) || $shapeX === [] || $shapeY === []) {
            return 0.0;
        }

        $foregroundWidth = count(array_filter($shapeX, fn (float $value): bool => $value > 0.18));
        $foregroundHeight = count(array_filter($shapeY, fn (float $value): bool => $value > 0.18));
        $foregroundAspect = $foregroundWidth / max($foregroundHeight, 1);
        $topWeight = array_sum(array_slice($shapeY, 0, intdiv(count($shapeY), 2)));
        $bottomWeight = array_sum(array_slice($shapeY, intdiv(count($shapeY), 2)));
        $foregroundRatio = (float) ($features['foreground_ratio'] ?? 0.0);
        $edgeDensity = (float) ($features['edge_density'] ?? 0.0);

        $aspectScore = min(1.0, max(0.0, ($foregroundAspect - 0.9) / 1.2));
        $balanceScore = $bottomWeight > 0.0
            ? min(1.0, max(0.0, ($bottomWeight - $topWeight + 0.35) / 1.1))
            : 0.0;
        $coverageScore = min(1.0, max(0.0, ($foregroundRatio - 0.06) / 0.26));
        $edgeScore = min(1.0, max(0.0, $edgeDensity / 0.09));

        return round(min(0.99, max(0.01, (
            ($aspectScore * 0.34)
            + ($balanceScore * 0.28)
            + ($coverageScore * 0.24)
            + ($edgeScore * 0.14)
        ))), 6);
    }

    private function fakeBlurScore(array $features): float
    {
        return round(max(0.0001, (float) ($features['edge_density'] ?? 0.0)), 6);
    }
}
