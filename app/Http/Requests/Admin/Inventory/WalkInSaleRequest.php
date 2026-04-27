<?php

namespace App\Http\Requests\Admin\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WalkInSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $decodedLines = json_decode((string) $this->input('lines_json', '[]'), true);

        $this->merge([
            'customer_name' => trim((string) $this->input('customer_name')),
            'customer_phone' => trim((string) $this->input('customer_phone')),
            'notes' => trim((string) $this->input('notes')),
            'lines' => is_array($decodedLines) ? $decodedLines : [],
        ]);
    }

    public function rules(): array
    {
        return [
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:60'],
            'payment_method' => ['required', Rule::in(['cash', 'gcash', 'card', 'other'])],
            'payment_status' => ['required', Rule::in(['paid', 'pending', 'unpaid'])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines_json' => ['required', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
