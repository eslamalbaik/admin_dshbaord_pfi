<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'exchange_rate'  => 'nullable|numeric|min:0.0001|max:1000',
            'receipt_image'  => 'nullable|file|mimes:png,jpg,jpeg,webp,pdf|max:5120',
        ];
    }
}
