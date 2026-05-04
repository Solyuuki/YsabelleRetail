<?php

namespace App\Support\Storefront;

class ProductMediaPath
{
    public function toUrl(mixed $value): ?string
    {
        if ($path = $this->toRelativePath($value)) {
            return asset($path);
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if (! filter_var($value, FILTER_VALIDATE_URL)) {
            return null;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        return $value;
    }

    public function toRelativePath(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

            if (! in_array($scheme, ['http', 'https'], true)) {
                return null;
            }

            $host = strtolower((string) parse_url($value, PHP_URL_HOST));

            if (! $this->isLocalHost($host)) {
                return null;
            }

            return $this->normalizeRelativePath((string) parse_url($value, PHP_URL_PATH));
        }

        return $this->normalizeRelativePath($value);
    }

    public function toLocalPublicPath(mixed $value): ?string
    {
        $relativePath = $this->toRelativePath($value);

        if (! is_string($relativePath) || $relativePath === '') {
            return null;
        }

        $candidate = public_path($relativePath);

        return is_file($candidate) ? $candidate : null;
    }

    public function toIdentity(mixed $value): ?string
    {
        if ($path = $this->toRelativePath($value)) {
            return 'local://'.$path;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if (! filter_var($value, FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($value);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'], $parts['path'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        $path = rtrim($parts['path'], '/');

        if ($path === '') {
            $path = '/';
        }

        return "{$scheme}://{$host}{$path}";
    }

    private function normalizeRelativePath(string $path): ?string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = preg_replace('#/+#', '/', $path);
        $path = ltrim((string) $path, '/');

        if ($path === '') {
            return null;
        }

        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $path) === 1) {
            return null;
        }

        if (str_contains($path, '../') || str_contains($path, '..\\') || $path === '..') {
            return null;
        }

        return $path;
    }

    private function isLocalHost(string $host): bool
    {
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $requestHost = request()?->getHost();

        return in_array(
            $host,
            array_filter([
                is_string($appHost) ? strtolower($appHost) : null,
                is_string($requestHost) ? strtolower($requestHost) : null,
                'localhost',
                '127.0.0.1',
            ]),
            true,
        );
    }
}
