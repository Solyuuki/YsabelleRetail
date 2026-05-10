<?php

namespace App\Services\Storefront;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VisualSearchImageSource
{
    public function loadFromUpload(UploadedFile $image): ?string
    {
        $materialized = $this->materializeFromUpload($image);

        if (! is_array($materialized) || ! is_string($materialized['path'] ?? null)) {
            return null;
        }

        try {
            $binary = @file_get_contents($materialized['path']);

            return is_string($binary) && $binary !== '' ? $binary : null;
        } finally {
            if (($materialized['temporary'] ?? false) === true && is_string($materialized['path'])) {
                @unlink($materialized['path']);
            }
        }
    }

    public function materializeFromUpload(UploadedFile $image): ?array
    {
        foreach ($this->uploadPathCandidates($image) as $candidate) {
            if (is_file($candidate['path']) && is_readable($candidate['path'])) {
                return [
                    'path' => $candidate['path'],
                    'relative_path' => null,
                    'disk' => $candidate['disk'],
                    'temporary' => false,
                ];
            }
        }

        $binary = $this->binaryFromUpload($image);

        if (! is_string($binary) || $binary === '') {
            return null;
        }

        $path = $this->writeTemporaryBinary($binary, $this->extensionFromUpload($image), 'visual-search-upload-');

        if ($path === null) {
            return null;
        }

        return [
            'path' => $path,
            'relative_path' => null,
            'disk' => 'upload-temporary',
            'temporary' => true,
        ];
    }

    public function loadFromUrl(string $url): ?string
    {
        if ($resolved = $this->resolveLocalPath($url)) {
            $binary = @file_get_contents($resolved['path']);

            return is_string($binary) && $binary !== '' ? $binary : null;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(['Accept' => 'image/*'])
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            $contentType = strtolower((string) $response->header('Content-Type', ''));

            if ($contentType !== '' && ! str_starts_with($contentType, 'image/')) {
                return null;
            }

            $body = $response->body();

            return $body !== '' ? $body : null;
        } catch (\Throwable $exception) {
            Log::warning('visual-search.image_fetch_failed', [
                'url' => $url,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function materializeFromUrl(string $url): ?array
    {
        if ($resolved = $this->resolveLocalPath($url)) {
            return [
                'path' => $resolved['path'],
                'relative_path' => $resolved['relative_path'],
                'disk' => $resolved['disk'],
                'temporary' => false,
            ];
        }

        $binary = $this->loadFromUrl($url);

        if (! is_string($binary) || $binary === '') {
            return null;
        }

        $extension = $this->extensionFromUrl($url);
        $directory = storage_path('app/visual-search');

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $path = tempnam($directory, 'visual-search-');

        if (! is_string($path) || $path === '') {
            return null;
        }

        $finalPath = $path.'.'.$extension;
        @rename($path, $finalPath);
        file_put_contents($finalPath, $binary);

        return [
            'path' => $finalPath,
            'relative_path' => null,
            'disk' => 'temporary',
            'temporary' => true,
        ];
    }

    public function resolveLocalPath(string $url): ?array
    {
        $parsedHost = parse_url($url, PHP_URL_HOST);
        $parsedPath = parse_url($url, PHP_URL_PATH);
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (! is_string($parsedPath) || $parsedPath === '') {
            return null;
        }

        $allowedHosts = array_filter([
            is_string($appHost) ? strtolower($appHost) : null,
            '127.0.0.1',
            'localhost',
        ]);

        if ($parsedHost !== null && ! in_array(strtolower((string) $parsedHost), $allowedHosts, true)) {
            return null;
        }

        $relativePath = ltrim($parsedPath, '/');
        $candidates = [
            [
                'path' => public_path($relativePath),
                'relative_path' => $relativePath,
                'disk' => 'public',
            ],
        ];

        if (str_starts_with($relativePath, 'storage/')) {
            $storageRelativePath = ltrim(substr($relativePath, strlen('storage/')), '/');
            $candidates[] = [
                'path' => storage_path('app/public/'.$storageRelativePath),
                'relative_path' => $relativePath,
                'disk' => 'public-storage',
            ];
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate['path'])) {
                return $candidate;
            }
        }

        return null;
    }

    private function extensionFromUrl(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) ? $extension : 'img';
    }

    private function extensionFromUpload(UploadedFile $image): string
    {
        $extension = Str::lower((string) $image->getClientOriginalExtension());

        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) ? $extension : 'img';
    }

    private function uploadPathCandidates(UploadedFile $image): array
    {
        $candidates = [];

        foreach (array_unique(array_filter([
            $image->getRealPath(),
            $image->getPathname(),
        ])) as $path) {
            $candidates[] = [
                'path' => $path,
                'disk' => $path === $image->getRealPath() ? 'upload-realpath' : 'upload-pathname',
            ];
        }

        return $candidates;
    }

    private function binaryFromUpload(UploadedFile $image): ?string
    {
        foreach ($this->uploadPathCandidates($image) as $candidate) {
            if (! is_file($candidate['path']) || ! is_readable($candidate['path'])) {
                continue;
            }

            $binary = @file_get_contents($candidate['path']);

            if (is_string($binary) && $binary !== '') {
                return $binary;
            }
        }

        return null;
    }

    private function writeTemporaryBinary(string $binary, string $extension, string $prefix): ?string
    {
        $directory = storage_path('app/visual-search');

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $path = tempnam($directory, $prefix);

        if (! is_string($path) || $path === '') {
            return null;
        }

        $finalPath = $path.'.'.$extension;
        @rename($path, $finalPath);
        file_put_contents($finalPath, $binary);

        return $finalPath;
    }
}
