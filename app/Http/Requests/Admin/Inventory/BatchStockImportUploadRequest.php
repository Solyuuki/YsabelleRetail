<?php

namespace App\Http\Requests\Admin\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class BatchStockImportUploadRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->hasSession()) {
            $this->session()->forget('inventory_import_preview');
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,xlsx,xls', 'max:10240'],
        ];
    }
}
