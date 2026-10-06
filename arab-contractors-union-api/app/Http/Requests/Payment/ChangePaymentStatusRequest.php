<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class ChangePaymentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'        => 'required|in:pending,paid,rejected',
            'reason'        => 'required|string|max:500',
            'exchange_rate' => 'nullable|numeric|min:0.0001|max:1000',
            'receipt_image' => 'nullable|file|mimes:png,jpg,jpeg,webp,pdf|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'سبب تغيير الحالة مطلوب.',
        ];
    }
}
