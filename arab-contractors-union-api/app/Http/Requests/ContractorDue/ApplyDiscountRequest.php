<?php

namespace App\Http\Requests\ContractorDue;

use Illuminate\Foundation\Http\FormRequest;

class ApplyDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'discount_type'   => 'required|in:percent,fixed',
            'discount_value'  => 'required|numeric|min:0.01',
            'discount_reason' => 'nullable|string|max:255',
        ];
    }
}
