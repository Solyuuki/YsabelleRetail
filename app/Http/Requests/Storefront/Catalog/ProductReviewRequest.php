<?php

namespace App\Http\Requests\Storefront\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class ProductReviewRequest extends FormRequest
{
    protected $errorBag = 'review';

    public function authorize(): bool
    {
        return $this->user()?->isCustomer() ?? false;
    }

    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'min:20', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => $this->normalizeText($this->input('title')),
            'body' => $this->normalizeText($this->input('body')),
        ]);
    }

    private function normalizeText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = preg_replace("/\r\n?/", "\n", strip_tags(trim((string) $value)));

        return $normalized === '' ? null : $normalized;
    }
}
