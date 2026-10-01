<?php

namespace App\Http\Requests\Certificate;

use Illuminate\Foundation\Http\FormRequest;

class StoreCertificateRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'       => 'required|in:membership,good_standing,classification,experience',
            'notes'      => 'nullable|string|max:500',
            'attachment' => 'nullable|file|mimes:png,jpg,jpeg,webp,pdf|max:5120',
        ];
    }
}
