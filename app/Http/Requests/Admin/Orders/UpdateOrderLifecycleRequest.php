<?php

namespace App\Http\Requests\Admin\Orders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderLifecycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['pending', 'processing', 'completed'])],
            'payment_status' => ['required', Rule::in(['unpaid', 'pending', 'paid'])],
        ];
    }
}
