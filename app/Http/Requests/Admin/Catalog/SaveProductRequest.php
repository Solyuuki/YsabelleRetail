<?php

namespace App\Http\Requests\Admin\Catalog;

use App\Models\Catalog\ProductVariant;
use App\Support\Storefront\ProductMediaPath;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveProductRequest extends FormRequest
{
    private const ALLOWED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
    private const ALLOWED_IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/webp',
    ];

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $variants = collect($this->input('variants', []))
            ->map(function (array $variant): array {
                return [
                    'id' => $variant['id'] ?? null,
                    'name' => trim((string) ($variant['name'] ?? '')),
                    'sku' => trim((string) ($variant['sku'] ?? '')),
                    'barcode' => trim((string) ($variant['barcode'] ?? '')),
                    'size' => trim((string) ($variant['size'] ?? '')),
                    'color' => trim((string) ($variant['color'] ?? '')),
                    'price' => $variant['price'] ?? null,
                    'compare_at_price' => $variant['compare_at_price'] ?? null,
                    'cost_price' => $variant['cost_price'] ?? null,
                    'supplier_name' => trim((string) ($variant['supplier_name'] ?? '')),
                    'weight_grams' => $variant['weight_grams'] ?? null,
                    'status' => $variant['status'] ?? 'active',
                    'quantity_on_hand' => $variant['quantity_on_hand'] ?? 0,
                    'reorder_level' => $variant['reorder_level'] ?? 0,
                    'allow_backorder' => filter_var($variant['allow_backorder'] ?? false, FILTER_VALIDATE_BOOL),
                ];
            })
            ->values()
            ->all();

        $this->merge([
            'slug' => trim((string) $this->input('slug')),
            'primary_image_url' => trim((string) $this->input('primary_image_url', '')),
            'variants' => $variants,
            'is_featured' => $this->boolean('is_featured'),
            'force_new_badge' => $this->boolean('force_new_badge'),
            'remove_primary_image' => $this->boolean('remove_primary_image'),
            'track_inventory' => $this->boolean('track_inventory', true),
        ]);
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id;

        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($productId)],
            'style_code' => ['nullable', 'string', 'max:255', Rule::unique('products', 'style_code')->ignore($productId)],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'primary_image_upload' => [
                'nullable',
                'file',
                'max:5120',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null) {
                        return;
                    }

                    if (! $value instanceof UploadedFile || ! $this->isSupportedImage($value)) {
                        $fail('Please upload a JPG, JPEG, PNG, or WEBP image up to 5 MB.');
                    }
                },
            ],
            'primary_image_url' => [
                'nullable',
                'string',
                'max:2048',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    if ($this->hasFile('primary_image_upload')) {
                        return;
                    }

                    $path = app(ProductMediaPath::class);

                    if ($path->toUrl($value) === null) {
                        $fail('The primary image must be a valid HTTP(S) URL or a local public asset path such as images/products/example.jpg.');
                    }
                },
            ],
            'image_alt' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['draft', 'active', 'archived'])],
            'is_featured' => ['required', 'boolean'],
            'force_new_badge' => ['required', 'boolean'],
            'remove_primary_image' => ['required', 'boolean'],
            'featured_rank' => ['nullable', 'integer', 'min:1', 'max:999'],
            'track_inventory' => ['required', 'boolean'],
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'variants.*.name' => ['required', 'string', 'max:255'],
            'variants.*.sku' => ['required', 'string', 'max:255'],
            'variants.*.barcode' => ['nullable', 'string', 'max:255'],
            'variants.*.size' => ['nullable', 'string', 'max:60'],
            'variants.*.color' => ['nullable', 'string', 'max:60'],
            'variants.*.price' => ['required', 'numeric', 'min:0'],
            'variants.*.compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.supplier_name' => ['nullable', 'string', 'max:255'],
            'variants.*.weight_grams' => ['nullable', 'integer', 'min:0'],
            'variants.*.status' => ['required', Rule::in(['active', 'archived'])],
            'variants.*.quantity_on_hand' => ['required', 'integer', 'min:0'],
            'variants.*.reorder_level' => ['required', 'integer', 'min:0'],
            'variants.*.allow_backorder' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $rows = collect($this->input('variants', []));
                $skus = $rows->pluck('sku')->filter();

                if ($skus->duplicates()->isNotEmpty()) {
                    $validator->errors()->add('variants', 'Variant SKUs must be unique within the product form.');
                }

                $conflicts = ProductVariant::query()
                    ->whereIn('sku', $skus->all())
                    ->when(
                        $rows->pluck('id')->filter()->isNotEmpty(),
                        fn ($query) => $query->whereNotIn('id', $rows->pluck('id')->filter()->all())
                    )
                    ->pluck('sku')
                    ->all();

                if ($conflicts !== []) {
                    $validator->errors()->add('variants', 'These SKUs already exist: '.implode(', ', $conflicts).'.');
                }
            },
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

        $dimensions = @getimagesize($this->inspectablePath($image) ?: '');

        return is_array($dimensions) && str_starts_with(Str::lower((string) ($dimensions['mime'] ?? '')), 'image/');
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
}
