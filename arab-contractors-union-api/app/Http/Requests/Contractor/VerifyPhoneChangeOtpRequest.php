<?php

namespace App\Http\Requests\Contractor;

use Illuminate\Foundation\Http\FormRequest;

class VerifyPhoneChangeOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'otp' => 'required|string|size:6',
        ];
    }
}
