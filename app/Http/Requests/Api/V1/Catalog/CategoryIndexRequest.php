<?php

namespace App\Http\Requests\Api\V1\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class CategoryIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
