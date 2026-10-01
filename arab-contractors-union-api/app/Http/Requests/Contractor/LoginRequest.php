<?php

namespace App\Http\Requests\Contractor;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'membership_number' => 'required|string',
            'password'          => 'required|string',
            'fcm_token'         => 'nullable|string',
            'device_name'       => 'nullable|string|max:120',
        ];
    }
}
