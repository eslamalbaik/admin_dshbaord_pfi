<?php

namespace App\Http\Requests\ContractorDue;

use Illuminate\Foundation\Http\FormRequest;

class GenerateFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year'             => 'required|integer|between:1990,2100',
            'discount_type'    => 'nullable|in:percent,fixed',
            'discount_value'   => 'required_with:discount_type|nullable|numeric|min:0.01',
            'discount_reason'  => 'nullable|string|max:255',
        ];
    }
}
