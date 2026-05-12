<?php

namespace App\Http\Requests\Storefront\Assistant;

use Illuminate\Foundation\Http\FormRequest;

class StorefrontAssistantMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:400'],
            'page_context' => ['sometimes', 'array'],
            'page_context.current_product' => ['sometimes', 'array'],
            'page_context.current_product.slug' => ['nullable', 'string', 'max:255'],
            'page_context.current_product.name' => ['nullable', 'string', 'max:255'],
            'page_context.current_product.style_code' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function assistantContext(): array
    {
        $context = $this->input('assistant_context');

        return is_array($context) ? $context : [];
    }

    public function pageContext(): array
    {
        $context = $this->input('page_context');

        if (! is_array($context)) {
            return [];
        }

        $currentProduct = $context['current_product'] ?? null;

        if (! is_array($currentProduct)) {
            return [];
        }

        return [
            'current_product' => array_filter([
                'slug' => trim((string) ($currentProduct['slug'] ?? '')) ?: null,
                'name' => trim((string) ($currentProduct['name'] ?? '')) ?: null,
                'style_code' => trim((string) ($currentProduct['style_code'] ?? '')) ?: null,
            ]),
        ];
    }
}
