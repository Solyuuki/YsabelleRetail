<?php

namespace App\Http\Requests\Storefront\Checkout;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'full_name' => $this->trimStringInput('full_name'),
            'email' => $this->normalizeEmail('email'),
            'phone' => $this->trimStringInput('phone'),
            'city' => $this->trimStringInput('city'),
            'address' => $this->trimStringInput('address'),
            'postal_code' => $this->trimStringInput('postal_code'),
            'order_notes' => $this->trimStringInput('order_notes'),
            'payment_method' => match ($this->input('payment_method')) {
                'cash_on_delivery' => 'cod',
                'card' => 'card_simulated',
                default => $this->input('payment_method'),
            },
        ]);
    }

    public function rules(): array
    {
        return [
            'full_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'city' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'order_notes' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['nullable', Rule::in(['cod', 'card_simulated'])],
        ];
    }

    private function trimStringInput(string $key): mixed
    {
        return is_string($this->input($key))
            ? trim($this->input($key))
            : $this->input($key);
    }

    private function normalizeEmail(string $key): mixed
    {
        $value = $this->trimStringInput($key);

        return is_string($value) ? strtolower($value) : $value;
    }
}
