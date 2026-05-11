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
        private readonly VisualSearchImageSource $imageSource,
    ) {}

    public function health(): array
    {
        $runtime = $this->runtimeDetails();

        if (! $this->enabled()) {
            return [
                'configured' => false,
                'available' => false,
                'reachable' => false,
                'model' => $this->model(),
                'embedding_version' => $this->embeddingVersion(),
                'service' => 'python-cli',
                'message' => 'Embedding service is disabled.',
                'python_binary' => $runtime['configured_python_binary'] ?? null,
                'env_file_python_binary' => $runtime['env_file_python_binary'] ?? null,
                'python_binary_exists' => $runtime['configured_python_binary_exists'] ?? false,
                'script_path' => $runtime['script_path'] ?? null,
                'script_exists' => $runtime['script_exists'] ?? false,
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
                'python_binary' => $payload['python_binary'] ?? ($runtime['configured_python_binary'] ?? null),
                'env_file_python_binary' => $runtime['env_file_python_binary'] ?? null,
                'python_binary_exists' => $runtime['configured_python_binary_exists'] ?? false,
                'script_path' => $runtime['script_path'] ?? null,
                'script_exists' => $runtime['script_exists'] ?? false,
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
                'python_binary' => $runtime['configured_python_binary'] ?? null,
                'env_file_python_binary' => $runtime['env_file_python_binary'] ?? null,
                'python_binary_exists' => $runtime['configured_python_binary_exists'] ?? false,
                'script_path' => $runtime['script_path'] ?? null,
                'script_exists' => $runtime['script_exists'] ?? false,
            ];
        }
    }

    public function runtimeDetails(bool $probeDependencies = false): array
    {
        $configuredPythonBinary = $this->configuredPythonBinary();
        $envFilePythonBinary = $this->envFileValue('STOREFRONT_VISUAL_SEARCH_PYTHON');
        $scriptPath = $this->scriptPath();
        $candidates = $this->pythonBinaryCandidates();
        $resolvedPythonBinary = $this->firstReachablePythonBinary($candidates) ?? ($candidates[0] ?? $configuredPythonBinary);

        $runtime = [
            'configured_python_binary' => $configuredPythonBinary,
            'configured_python_binary_exists' => $this->binaryExists($configuredPythonBinary),
            'env_file_python_binary' => $envFilePythonBinary,
            'env_file_python_binary_exists' => $this->binaryExists($envFilePythonBinary),
            'python_binary_candidates' => $candidates,
            'resolved_python_binary' => $resolvedPythonBinary,
            'script_path' => $scriptPath,
            'script_exists' => is_file($scriptPath),
            'model' => $this->model(),
            'embedding_version' => $this->embeddingVersion(),
        ];

        if (! $probeDependencies || $resolvedPythonBinary === null || $resolvedPythonBinary === '') {
            return $runtime;
        }

        $pythonVersion = $this->probePythonVersion($resolvedPythonBinary);

        return $runtime + [
            'python_version' => $pythonVersion['message'],
            'python_version_ok' => $pythonVersion['ok'],
            'dependencies' => [
                'numpy' => $this->probePythonImport($resolvedPythonBinary, 'numpy'),
                'torch' => $this->probePythonImport($resolvedPythonBinary, 'torch'),
                'transformers' => $this->probePythonImport($resolvedPythonBinary, 'transformers'),
            ],
            'recommended_fix' => $this->recommendedFix($runtime, $pythonVersion),
        ];
    }

    public function embedUpload(UploadedFile $image): ?array
    {
        $materialized = $this->imageSource->materializeFromUpload($image);

        if (! is_array($materialized) || ! is_string($materialized['path'] ?? null)) {
            return null;
        }

        try {
            return $this->embedPath($materialized['path']);
        } finally {
            if (($materialized['temporary'] ?? false) === true && is_string($materialized['path'])) {
                @unlink($materialized['path']);
            }
        }
    }

    public function embedPath(string $path, string $id = 'upload'): ?array
    {
        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $result = $this->embedPaths([[
            'id' => $id,
            'path' => $path,
        ]]);

        return $result[$id] ?? null;
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
            $payload = $this->runCommand(['embed-batch', '--input', $payloadPath], $this->batchTimeoutSeconds(count($normalized)));

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

    public function batchTimeoutSeconds(int $itemCount): int
    {
        $baseline = $this->timeoutSeconds();

        if ($itemCount <= 1) {
            return $baseline;
        }

        return max($baseline, 60 + ($itemCount * 4));
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
        $attempts = [];

        foreach ($this->pythonBinaryCandidates() as $pythonBinary) {
            try {
                $payload = $this->runCommandWithBinary($pythonBinary, $arguments, $timeout);
                $payload['python_binary'] = $pythonBinary;
                $payload['python_attempts'] = $attempts;

                return $payload;
            } catch (\Throwable $exception) {
                $attempts[] = [
                    'python_binary' => $pythonBinary,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        $messages = collect($attempts)
            ->map(fn (array $attempt): string => sprintf(
                '[%s] %s',
                $attempt['python_binary'] ?: 'unknown-python',
                $attempt['message'] ?: 'unknown error',
            ))
            ->implode(' | ');

        throw new \RuntimeException($messages !== '' ? $messages : 'Embedding service command failed.');
    }

    private function runCommandWithBinary(string $pythonBinary, array $arguments, ?int $timeout = null): array
    {
        $command = array_merge(
            [
                $pythonBinary,
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
            'PYTHONHOME' => false,
            'PYTHONPATH' => false,
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

    private function configuredPythonBinary(): string
    {
        return trim((string) config('storefront.assistant.visual_search.embedding.python_binary', 'python'));
    }

    private function pythonBinaryCandidates(): array
    {
        return collect([
            $this->configuredPythonBinary(),
            $this->envFileValue('STOREFRONT_VISUAL_SEARCH_PYTHON'),
        ])
            ->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '')
            ->map(fn (string $value): string => trim($value))
            ->unique()
            ->values()
            ->all();
    }

    private function envFileValue(string $key): ?string
    {
        $path = base_path('.env');

        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $trimmed = trim((string) $line);

            if ($trimmed === '' || str_starts_with($trimmed, '#') || ! str_starts_with($trimmed, $key.'=')) {
                continue;
            }

            return trim(trim(substr($trimmed, strlen($key) + 1)), " \t\n\r\0\x0B\"'");
        }

        return null;
    }

    private function binaryExists(?string $pythonBinary): bool
    {
        if (! is_string($pythonBinary) || trim($pythonBinary) === '') {
            return false;
        }

        $normalized = trim($pythonBinary);

        if ($this->looksLikePath($normalized)) {
            return is_file($normalized);
        }

        return $this->probeCommand($normalized, ['--version'])['ok'];
    }

    private function firstReachablePythonBinary(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            if ($this->probeCommand($candidate, ['--version'])['ok']) {
                return $candidate;
            }
        }

        return null;
    }

    private function probePythonVersion(string $pythonBinary): array
    {
        return $this->probeCommand($pythonBinary, ['--version']);
    }

    private function probePythonImport(string $pythonBinary, string $module): array
    {
        return $this->probeCommand($pythonBinary, [
            '-c',
            sprintf("import %s; print(getattr(%s, '__version__', 'ok'))", $module, $module),
        ]);
    }

    private function probeCommand(string $pythonBinary, array $arguments): array
    {
        try {
            $process = new Process(array_merge([$pythonBinary], $arguments), base_path(), [
                'PYTHONIOENCODING' => 'utf-8',
                'PYTHONHOME' => false,
                'PYTHONPATH' => false,
            ]);
            $process->setTimeout(20);
            $process->run();

            if (! $process->isSuccessful()) {
                $message = trim($process->getErrorOutput()) ?: trim($process->getOutput()) ?: 'command failed';

                return [
                    'ok' => false,
                    'message' => $message,
                ];
            }

            $message = trim($process->getOutput()) ?: trim($process->getErrorOutput()) ?: 'ok';

            return [
                'ok' => true,
                'message' => $message,
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'message' => $exception->getMessage(),
            ];
        }
    }

    private function recommendedFix(array $runtime, array $pythonVersion): string
    {
        if (! ($runtime['script_exists'] ?? false)) {
            return 'Fix STOREFRONT_VISUAL_SEARCH_SCRIPT so it points to tools/visual_search_embedding_service.py.';
        }

        if (! ($pythonVersion['ok'] ?? false)) {
            return 'Fix STOREFRONT_VISUAL_SEARCH_PYTHON so the served app can execute the configured Python binary.';
        }

        $dependencies = $runtime['dependencies'] ?? [];
        $missing = collect($dependencies)
            ->filter(fn (array $probe): bool => ($probe['ok'] ?? false) !== true)
            ->keys()
            ->values()
            ->all();

        if ($missing !== []) {
            return 'Install missing Python packages for visual search: '.implode(', ', $missing).'.';
        }

        return 'Runtime looks healthy. If HTTP requests still disagree with CLI health, clear Laravel caches and restart the served PHP process.';
    }

    private function looksLikePath(string $value): bool
    {
        return str_contains($value, '\\')
            || str_contains($value, '/')
            || preg_match('/^[A-Za-z]:/', $value) === 1
            || Str::endsWith(Str::lower($value), '.exe');
    }

    private function scriptPath(): string
    {
        return (string) config('storefront.assistant.visual_search.embedding.script', base_path('tools/visual_search_embedding_service.py'));
    }
}
