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
            'customer_email' => filled($this->input('customer_email'))
                ? mb_strtolower(trim((string) $this->input('customer_email')))
                : '',
            'customer_phone' => trim((string) $this->input('customer_phone')),
            'notes' => trim((string) $this->input('notes')),
            'discount_amount' => $this->input('discount_amount'),
            'lines' => is_array($decodedLines) ? $decodedLines : [],
        ]);
    }

    public function rules(): array
    {
        return [
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => [
                'nullable',
                'string',
                'max:40',
                'regex:/^\+?[\d\s\-\(\)]+$/',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || $value === '') {
                        return;
                    }

                    $digitsOnly = preg_replace('/\D+/', '', $value) ?? '';
                    $digitCount = strlen($digitsOnly);

                    if ($digitCount < 7) {
                        $fail('The customer phone must contain at least 7 digits.');
                    }

                    if ($digitCount > 20) {
                        $fail('The customer phone must not contain more than 20 digits.');
                    }
                },
            ],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
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
