<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class SubmitTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'           => 'required|numeric|min:1',
            'currency'         => 'nullable|in:JOD,ILS,USD',
            'receipt_image'    => 'required|file|mimes:png,jpg,jpeg,webp,pdf|max:5120',
            'bank_account_id'  => 'nullable|exists:bank_accounts,id',
            'membership_id'    => 'nullable|exists:memberships,id',
            'equipment_package_id' => 'nullable|exists:equipment_packages,id',
            'reference_number' => 'nullable|string|max:100',
            'notes'            => 'nullable|string|max:500',
            'type'             => 'nullable|string|max:50',
        ];
    }
}
