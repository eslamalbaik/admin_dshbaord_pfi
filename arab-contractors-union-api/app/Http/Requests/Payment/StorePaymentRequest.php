<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contractor_id'    => 'required|exists:contractors,id',
            'membership_id'    => 'nullable|exists:memberships,id',
            'amount'           => 'required|numeric|min:0',
            'currency'         => 'nullable|in:JOD,ILS,USD',
            'exchange_rate'    => 'nullable|numeric|min:0.0001|max:1000',
            'type'             => 'nullable|string',
            'status'           => 'nullable|in:pending,paid,refunded,failed,rejected',
            'method'           => 'nullable|string',
            'reference_number' => 'nullable|string',
            'notes'            => 'nullable|string',
        ];
    }
}
