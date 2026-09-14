<?php

namespace App\Http\Requests\Certificate;

use Illuminate\Foundation\Http\FormRequest;

class AdminIssueMembershipCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contractor_id'   => 'required|integer|exists:contractors,id',
            'notes'           => 'nullable|string|max:500',
            'address'         => 'nullable|string|max:255',
            'decision_number' => 'nullable|string|max:100',
            'decision_date'   => 'nullable|date',
        ];
    }
}
