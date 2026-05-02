<?php

namespace App\Http\Requests\Storefront\Assistant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class StorefrontVisualSearchRequest extends FormRequest
{
    private const ALLOWED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'];

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

        if (str_starts_with($detectedMime, 'image/')) {
            return true;
        }

        if (str_starts_with($clientMime, 'image/')) {
            return true;
        }

        return in_array($extension, self::ALLOWED_IMAGE_EXTENSIONS, true);
    }
}
