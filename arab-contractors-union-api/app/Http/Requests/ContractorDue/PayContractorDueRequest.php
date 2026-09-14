<?php

namespace App\Http\Requests\ContractorDue;

use Illuminate\Foundation\Http\FormRequest;

class PayContractorDueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'           => 'required|numeric|min:0.01',
            'currency'         => 'nullable|in:JOD,ILS,USD',
            'exchange_rate'    => 'nullable|numeric|min:0.0001|max:1000',
            'method'           => 'nullable|in:cash,bank_transfer,cheque',
            'reference_number' => 'nullable|string|max:100',
            'notes'            => 'nullable|string|max:500',
        ];
    }
}
