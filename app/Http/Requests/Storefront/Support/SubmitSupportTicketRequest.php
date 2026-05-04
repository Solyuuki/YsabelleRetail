<?php

namespace App\Http\Requests\Storefront\Support;

use App\Support\SupportTicketCategories;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'category' => is_string($this->input('category'))
                ? trim($this->input('category'))
                : $this->input('category'),
            'name' => is_string($this->input('name'))
                ? trim($this->input('name'))
                : $this->input('name'),
            'reply_email' => is_string($this->input('reply_email'))
                ? trim($this->input('reply_email'))
                : $this->input('reply_email'),
            'reference' => is_string($this->input('reference'))
                ? trim($this->input('reference'))
                : $this->input('reference'),
            'message' => is_string($this->input('message'))
                ? trim($this->input('message'))
                : $this->input('message'),
            'website' => is_string($this->input('website'))
                ? trim($this->input('website'))
                : $this->input('website'),
        ]);
    }

    public function rules(): array
    {
        return [
            'category' => ['required', 'string', 'max:80', Rule::in(SupportTicketCategories::keys())],
            'name' => ['required', 'string', 'max:120'],
            'reply_email' => ['required', 'email:rfc', 'max:180'],
            'reference' => ['nullable', 'string', 'max:120'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
            'website' => ['nullable', 'string', 'max:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'category.in' => 'Choose a valid support category.',
            'name.required' => 'Enter your name before sending the support request.',
            'reply_email.required' => 'Enter a reply email before sending the support request.',
            'reply_email.email' => 'Enter a valid reply email before sending the support request.',
            'message.required' => 'Add issue details before sending the support request.',
            'message.min' => 'Add at least 10 characters so support has enough detail.',
            'website.max' => 'The support request could not be submitted.',
        ];
    }
}
