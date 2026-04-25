<?php

namespace App\Http\Requests\Storefront\Checkout;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $paymentMethod = match ($this->input('payment_method')) {
            'cash_on_delivery' => 'cod',
            'card' => 'card_simulated',
            default => $this->input('payment_method'),
        };

        $this->merge([
            'payment_method' => $paymentMethod,
            'card_number' => is_string($this->input('card_number'))
                ? preg_replace('/\D+/', '', $this->input('card_number'))
                : $this->input('card_number'),
            'card_expiry' => is_string($this->input('card_expiry'))
                ? strtoupper(trim($this->input('card_expiry')))
                : $this->input('card_expiry'),
            'cardholder_name' => is_string($this->input('cardholder_name'))
                ? trim($this->input('cardholder_name'))
                : $this->input('cardholder_name'),
        ]);
    }

    public function rules(): array
    {
        $isSimulatedCard = $this->input('payment_method') === 'card_simulated';

        return [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'city' => ['required', 'string', 'max:120'],
            'address' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:20'],
            'order_notes' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['required', Rule::in(['cod', 'card_simulated'])],
            'cardholder_name' => [Rule::requiredIf($isSimulatedCard), 'nullable', 'string', 'max:255'],
            'card_number' => [Rule::requiredIf($isSimulatedCard), 'nullable', 'digits_between:13,19'],
            'card_expiry' => [Rule::requiredIf($isSimulatedCard), 'nullable', 'regex:/^(0[1-9]|1[0-2])\/\d{2}$/'],
            'card_cvc' => [Rule::requiredIf($isSimulatedCard), 'nullable', 'digits_between:3,4'],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_method.in' => 'Select a valid payment method.',
            'cardholder_name.required' => 'Enter the cardholder name for the simulated card flow.',
            'card_number.required' => 'Enter the simulated card number.',
            'card_number.digits_between' => 'Use a valid test card number.',
            'card_expiry.required' => 'Enter the simulated card expiry in MM/YY format.',
            'card_expiry.regex' => 'Use the MM/YY format for the simulated expiry date.',
            'card_cvc.required' => 'Enter the simulated card security code.',
            'card_cvc.digits_between' => 'Use a valid 3 or 4 digit security code.',
        ];
    }
}
