<?php

namespace App\Http\Requests\ContractorDue;

use Illuminate\Foundation\Http\FormRequest;

class SettleContractorDueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount_jod' => 'nullable|numeric|min:0.01',
            'payment_id' => 'nullable|exists:payments,id',
            'notes'      => 'nullable|string|max:500',
        ];
    }
}
