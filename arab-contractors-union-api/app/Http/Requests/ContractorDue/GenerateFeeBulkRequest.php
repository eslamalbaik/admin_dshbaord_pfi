<?php

namespace App\Http\Requests\ContractorDue;

use Illuminate\Foundation\Http\FormRequest;

class GenerateFeeBulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year'            => 'required|integer|between:1990,2100',
            'contractor_ids'  => 'nullable|array',
            'contractor_ids.*' => 'integer|exists:contractors,id',
            'dry_run'         => 'boolean',
        ];
    }
}
