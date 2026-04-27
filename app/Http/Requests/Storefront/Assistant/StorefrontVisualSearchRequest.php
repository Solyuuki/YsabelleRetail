<?php

namespace App\Http\Requests\Storefront\Assistant;

use Illuminate\Foundation\Http\FormRequest;

class StorefrontVisualSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'brand_style' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:40'],
            'category' => ['nullable', 'string', 'exists:categories,slug'],
            'use_case' => ['nullable', 'string', 'max:40'],
        ];
    }
}
