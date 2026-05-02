<?php

namespace App\Services\Storefront;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class VisualSearchImageSource
{
    public function loadFromUpload(UploadedFile $image): ?string
    {
        $path = $image->getRealPath();

        if (! is_string($path) || $path === '' || ! is_file($path)) {
            return null;
        }

        $binary = @file_get_contents($path);

        return is_string($binary) && $binary !== '' ? $binary : null;
    }

    public function loadFromUrl(string $url): ?string
    {
        if ($path = $this->localPublicPath($url)) {
            $binary = @file_get_contents($path);

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
        } catch (\Throwable) {
            return null;
        }
    }

    public function materializeFromUrl(string $url): ?array
    {
        if ($path = $this->localPublicPath($url)) {
            return [
                'path' => $path,
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
            'temporary' => true,
        ];
    }

    private function localPublicPath(string $url): ?string
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

        $candidate = public_path(ltrim($parsedPath, '/'));

        return is_file($candidate) ? $candidate : null;
    }

    private function extensionFromUrl(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) ? $extension : 'img';
    }
}
