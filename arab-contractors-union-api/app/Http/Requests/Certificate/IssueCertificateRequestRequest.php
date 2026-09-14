<?php

namespace App\Http\Requests\Certificate;

use Illuminate\Foundation\Http\FormRequest;

class IssueCertificateRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'certificate' => 'required|file|mimes:pdf|max:10240', // 10 MB
        ];
    }
}
