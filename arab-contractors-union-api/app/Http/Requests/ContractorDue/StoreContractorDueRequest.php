<?php

namespace App\Http\Requests\ContractorDue;

use Illuminate\Foundation\Http\FormRequest;

class StoreContractorDueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contractor_id' => 'required|exists:contractors,id',
            'description'   => 'required|string|max:500',
            'amount_jod'    => 'required|numeric|min:0.01|max:99999999',
            'year'          => 'nullable|integer|between:1990,2100',
            'period'        => 'nullable|string|max:50',
            'due_date'      => 'nullable|date',
            'notes'         => 'nullable|string|max:2000',
        ];
    }
}
