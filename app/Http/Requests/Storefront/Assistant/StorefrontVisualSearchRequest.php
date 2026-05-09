<?php

namespace App\Http\Requests\Storefront\Assistant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class StorefrontVisualSearchRequest extends FormRequest
{
    private const ALLOWED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'];
    private const ALLOWED_IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/webp',
        'image/heic',
        'image/heif',
        'image/heic-sequence',
        'image/heif-sequence',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image' => [
                'required',
                'file',
                'max:10240',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value instanceof UploadedFile || ! $this->isSupportedImage($value)) {
                        $fail('Please upload a JPG, PNG, WEBP, or HEIC image.');
                    }
                },
            ],
            'brand_style' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:40'],
            'category' => ['nullable', 'string', 'exists:categories,slug'],
            'use_case' => ['nullable', 'string', 'max:40'],
        ];
    }

    private function isSupportedImage(UploadedFile $image): bool
    {
        $detectedMime = Str::lower((string) $image->getMimeType());
        $clientMime = Str::lower((string) $image->getClientMimeType());
        $extension = Str::lower((string) $image->getClientOriginalExtension());
        $contentMime = $this->contentMimeType($image);

        if (! $this->isAllowedMime($contentMime, $detectedMime, $clientMime, $extension)) {
            return false;
        }

        if ($contentMime !== null && $this->shouldProbeDimensions($contentMime, $extension)) {
            $dimensions = @getimagesize($this->inspectablePath($image) ?: '');

            return is_array($dimensions) && str_starts_with(Str::lower((string) ($dimensions['mime'] ?? '')), 'image/');
        }

        return true;
    }

    private function contentMimeType(UploadedFile $image): ?string
    {
        $path = $this->inspectablePath($image);

        if (! is_string($path) || $path === '' || ! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $mime = @mime_content_type($path);

        return is_string($mime) && $mime !== '' ? Str::lower($mime) : null;
    }

    private function inspectablePath(UploadedFile $image): ?string
    {
        $candidates = array_unique(array_filter([
            $image->getRealPath(),
            $image->getPathname(),
        ]));

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function isAllowedMime(?string $contentMime, string $detectedMime, string $clientMime, string $extension): bool
    {
        foreach (array_filter([$contentMime, $detectedMime, $clientMime]) as $mime) {
            if (in_array($mime, self::ALLOWED_IMAGE_MIME_TYPES, true)) {
                return true;
            }
        }

        return in_array($extension, self::ALLOWED_IMAGE_EXTENSIONS, true);
    }

    private function shouldProbeDimensions(string $contentMime, string $extension): bool
    {
        if (in_array($extension, ['heic', 'heif'], true)) {
            return false;
        }

        return ! in_array($contentMime, ['image/heic', 'image/heif', 'image/heic-sequence', 'image/heif-sequence'], true);
    }
}
