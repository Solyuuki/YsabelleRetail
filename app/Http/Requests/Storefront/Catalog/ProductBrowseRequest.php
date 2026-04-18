<?php

namespace App\Http\Requests\Storefront\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class ProductBrowseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'featured' => ['nullable', 'boolean'],
            'status' => ['nullable', 'in:draft,active,archived'],
        ];
    }
}
