<?php

namespace App\Http\Requests\Contractor;

use Illuminate\Foundation\Http\FormRequest;

class RequestPhoneChangeOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => 'required|string|max:20',
        ];
    }
}
