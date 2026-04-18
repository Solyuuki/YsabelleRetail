<?php

namespace App\Http\Requests\Api\V1\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class ProductIndexRequest extends FormRequest
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
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
