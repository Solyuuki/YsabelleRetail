<?php

namespace App\Http\Requests\Admin\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuditLogFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'action' => ['nullable', 'string', 'max:255'],
            'actor_id' => ['nullable', 'integer', 'exists:users,id'],
            'entity_type' => ['nullable', 'string', 'max:255'],
            'format' => ['nullable', Rule::in(['csv', 'pdf', 'xlsx'])],
        ];
    }
}
