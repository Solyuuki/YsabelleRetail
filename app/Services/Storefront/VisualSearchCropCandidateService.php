<?php

namespace App\Services\Storefront;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class VisualSearchCropCandidateService
{
    /**
     * @param  string  $imagePath  Path to the original image file
     * @param  array  $imageMetadata  Optional metadata like dimensions from embedding service
     * @return Collection  Collection of crop candidates: [label, path, temporary, width, height, x_offset, y_offset]
     */
    public function generateCropCandidates(string $imagePath, ?array $imageMetadata = null): Collection
    {
        if (! is_file($imagePath) || ! is_readable($imagePath)) {
            return collect();
        }

        $candidates = collect();

        try {
            $payload = $this->runCropCommand($imagePath, $imageMetadata);

            if (! is_array($payload) || ! ($payload['ok'] ?? false)) {
                Log::warning('visual-search.crop_generation_failed', [
                    'image_path' => $imagePath,
                    'error' => $payload['error'] ?? 'unknown',
                ]);

                return $candidates;
            }

            foreach ($payload['candidates'] ?? [] as $candidate) {
                if (! is_array($candidate) || ! isset($candidate['path'], $candidate['label'])) {
                    continue;
                }

                if (! is_file($candidate['path']) || ! is_readable($candidate['path'])) {
                    continue;
                }

                $candidates->push([
                    'label' => $candidate['label'],
                    'path' => $candidate['path'],
                    'temporary' => (bool) ($candidate['temporary'] ?? true),
                    'width' => (int) ($candidate['width'] ?? 0),
                    'height' => (int) ($candidate['height'] ?? 0),
                    'x_offset' => (int) ($candidate['x_offset'] ?? 0),
                    'y_offset' => (int) ($candidate['y_offset'] ?? 0),
                    'confidence_hint' => $candidate['confidence_hint'] ?? null,
                ]);
            }
        } catch (\Throwable $exception) {
            Log::warning('visual-search.crop_candidate_exception', [
                'image_path' => $imagePath,
                'message' => $exception->getMessage(),
                'exception_class' => $exception::class,
            ]);
        }

        return $candidates;
    }

    /**
     * Clean up temporary crop files from a candidates collection.
     */
    public function cleanupCropCandidates(Collection $candidates): void
    {
        foreach ($candidates as $candidate) {
            if (
                ($candidate['temporary'] ?? false) === true
                && is_string($candidate['path'] ?? null)
                && is_file($candidate['path'])
            ) {
                @unlink($candidate['path']);
            }
        }
    }

    /**
     * Clean up a single crop candidate file.
     */
    public function cleanupCropFile(string $path): bool
    {
        if (is_file($path)) {
            return @unlink($path);
        }

        return true;
    }

    private function runCropCommand(string $imagePath, ?array $imageMetadata = null): array
    {
        $arguments = [
            $this->scriptPath(),
            'generate-crops',
            '--image',
            $imagePath,
            '--output-dir',
            $this->cropOutputDirectory(),
        ];

        if (is_array($imageMetadata)) {
            if (isset($imageMetadata['original_width'], $imageMetadata['original_height'])) {
                $arguments[] = '--original-width';
                $arguments[] = (string) $imageMetadata['original_width'];
                $arguments[] = '--original-height';
                $arguments[] = (string) $imageMetadata['original_height'];
            }
        }

        $command = array_merge(
            [
                (string) config('storefront.assistant.visual_search.embedding.python_binary', 'python'),
            ],
            $arguments,
        );

        $process = new Process($command, base_path(), [
            'PYTHONIOENCODING' => 'utf-8',
        ]);
        $process->setTimeout($this->timeoutSeconds());
        $process->run();

        if (! $process->isSuccessful()) {
            $errorOutput = trim($process->getErrorOutput());
            $output = trim($process->getOutput());
            $message = $errorOutput !== '' ? $errorOutput : $output;

            throw new \RuntimeException($message !== '' ? $message : 'Crop generation command failed.');
        }

        $payload = json_decode($process->getOutput(), true);

        if (! is_array($payload)) {
            throw new \RuntimeException('Crop generation service returned an invalid payload.');
        }

        if (($payload['ok'] ?? false) !== true) {
            throw new \RuntimeException((string) ($payload['error'] ?? 'Crop generation service reported a failure.'));
        }

        return $payload;
    }

    private function cropOutputDirectory(): string
    {
        $directory = storage_path('app/visual-search/crops');

        if (! is_dir($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        return $directory;
    }

    private function timeoutSeconds(): int
    {
        return max(10, (int) config('storefront.assistant.visual_search.crop_candidate.timeout', 60));
    }

    private function scriptPath(): string
    {
        return (string) config('storefront.assistant.visual_search.embedding.script', base_path('tools/visual_search_embedding_service.py'));
    }
}
