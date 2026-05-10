<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim($this->string('email')->toString())),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => [
                'required',
                'string',
                'confirmed',
                'min:8',
                'regex:/^(?=.*[A-Za-z])(?=.*\d).+$/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'password.confirmed' => 'Your password confirmation does not match.',
            'password.min' => 'The password must be at least 8 characters and include at least one letter and one number.',
            'password.regex' => 'The password must be at least 8 characters and include at least one letter and one number.',
        ];
    }

    public function credentials(): array
    {
        return $this->only('email', 'password', 'password_confirmation', 'token');
    }
}
