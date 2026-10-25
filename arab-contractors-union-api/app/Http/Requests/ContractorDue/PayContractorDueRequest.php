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
            'receipt_image'    => 'nullable|file|mimes:png,jpg,jpeg,webp,pdf|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'receipt_image.mimes' => 'صورة الإشعار يجب أن تكون صورة (png, jpg, jpeg, webp) أو ملف PDF.',
            'receipt_image.max'   => 'حجم صورة الإشعار يجب ألا يتجاوز 5 ميغابايت.',
        ];
    }
}
