<?php

namespace App\Http\Requests\Contractor;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function messages(): array
    {
        return [
            'identifier.required_without'        => 'أدخل رقم العضوية أو رقم جوال المفوض.',
            'membership_number.required_without' => 'أدخل رقم العضوية أو رقم جوال المفوض.',
        ];
    }

    public function rules(): array
    {
        return [
            // رقم العضوية أو جوال المفوض بالتوقيع — أي حقل من الاثنين (identifier هو الاسم العام الجديد)
            'identifier'        => 'required_without:membership_number|nullable|string|max:50',
            'membership_number' => 'required_without:identifier|nullable|string|max:50',
            'password'          => 'required|string',
            'fcm_token'         => 'nullable|string',
            'device_name'       => 'nullable|string|max:120',
        ];
    }
}
