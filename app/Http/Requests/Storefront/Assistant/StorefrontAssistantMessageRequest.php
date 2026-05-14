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
            'assistant_context.current_product' => ['sometimes', 'array'],
            'assistant_context.current_product.id' => ['nullable', 'integer'],
            'assistant_context.current_product.slug' => ['nullable', 'string', 'max:255'],
            'assistant_context.current_product.name' => ['nullable', 'string', 'max:255'],
            'assistant_context.current_product.style_code' => ['nullable', 'string', 'max:255'],
            'assistant_context.current_product.selected_color' => ['nullable', 'string', 'max:255'],
            'assistant_context.current_product.selected_color_label' => ['nullable', 'string', 'max:255'],
            'assistant_context.current_product.selected_size' => ['nullable', 'string', 'max:50'],
            'assistant_context.current_product.variant_id' => ['nullable', 'integer'],
            'page_context' => ['sometimes', 'array'],
            'page_context.current_product' => ['sometimes', 'array'],
            'page_context.current_product.id' => ['nullable', 'integer'],
            'page_context.current_product.slug' => ['nullable', 'string', 'max:255'],
            'page_context.current_product.name' => ['nullable', 'string', 'max:255'],
            'page_context.current_product.style_code' => ['nullable', 'string', 'max:255'],
            'page_context.current_product.selected_color' => ['nullable', 'string', 'max:255'],
            'page_context.current_product.selected_color_label' => ['nullable', 'string', 'max:255'],
            'page_context.current_product.selected_size' => ['nullable', 'string', 'max:50'],
            'page_context.current_product.variant_id' => ['nullable', 'integer'],
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

        return $this->sanitizeCurrentProductContext($context);
    }

    private function sanitizeCurrentProductContext(mixed $context): array
    {
        if (! is_array($context)) {
            return [];
        }

        $currentProduct = $context['current_product'] ?? null;

        if (! is_array($currentProduct)) {
            return [];
        }

        return [
            'current_product' => array_filter([
                'id' => ($id = (int) ($currentProduct['id'] ?? 0)) > 0 ? $id : null,
                'slug' => trim((string) ($currentProduct['slug'] ?? '')) ?: null,
                'name' => trim((string) ($currentProduct['name'] ?? '')) ?: null,
                'style_code' => trim((string) ($currentProduct['style_code'] ?? '')) ?: null,
                'selected_color' => trim((string) ($currentProduct['selected_color'] ?? '')) ?: null,
                'selected_color_label' => trim((string) ($currentProduct['selected_color_label'] ?? '')) ?: null,
                'selected_size' => trim((string) ($currentProduct['selected_size'] ?? '')) ?: null,
                'variant_id' => ($variantId = (int) ($currentProduct['variant_id'] ?? 0)) > 0 ? $variantId : null,
            ]),
        ];
    }
}
