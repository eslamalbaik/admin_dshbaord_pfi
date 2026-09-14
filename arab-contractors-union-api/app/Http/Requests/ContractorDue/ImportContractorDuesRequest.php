<?php

namespace App\Http\Requests\ContractorDue;

use Illuminate\Foundation\Http\FormRequest;

class ImportContractorDuesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file'           => 'required|file|mimes:xlsx,xls|max:10240',
            'dry_run'        => 'nullable|boolean',
            'force'          => 'nullable|boolean',
            'create_missing' => 'nullable|boolean',
        ];
    }
}
