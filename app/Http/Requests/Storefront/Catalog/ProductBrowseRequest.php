<?php

namespace App\Http\Requests\Storefront\Catalog;

use App\Support\Storefront\CatalogCollection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'category' => ['nullable', 'string', 'exists:categories,slug'],
            'use_case' => ['nullable', 'in:daily,running,walking,gym,performance'],
            'collection' => ['nullable', Rule::in(CatalogCollection::values())],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'featured' => ['nullable', 'boolean'],
            'status' => ['nullable', 'in:draft,active,archived'],
            'sort' => ['nullable', 'in:featured,price_asc,price_desc,newest,name,best_sellers'],
            'per_page' => ['nullable', 'integer', 'in:8,12'],
        ];
    }
}
