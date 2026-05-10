<?php

namespace App\Http\Requests\Storefront\Assistant;

use Illuminate\Foundation\Http\FormRequest;

class StorefrontAssistantMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:400'],
        ];
    }

    public function assistantContext(): array
    {
        $context = $this->input('assistant_context');

        return is_array($context) ? $context : [];
    }
}
