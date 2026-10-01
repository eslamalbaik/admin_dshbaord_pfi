<?php

namespace App\Http\Requests\Certificate;

use Illuminate\Foundation\Http\FormRequest;

class BulkDestroyCertificateRequestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids'   => 'required|array|min:1|max:200',
            'ids.*' => 'integer|exists:certificate_requests,id',
        ];
    }
}
