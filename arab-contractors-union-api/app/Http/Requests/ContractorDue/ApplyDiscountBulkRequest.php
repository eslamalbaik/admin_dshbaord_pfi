<?php

namespace App\Http\Requests\ContractorDue;

use Illuminate\Foundation\Http\FormRequest;

class ApplyDiscountBulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mode'                => 'required|in:ids,criteria',
            'ids'                 => 'required_if:mode,ids|array|max:200',
            'ids.*'               => 'integer|exists:contractor_dues,id',
            'criteria'            => 'required_if:mode,criteria|array',
            'criteria.year'       => 'nullable|integer|between:1990,2100',
            'criteria.status'     => 'nullable|in:unpaid,partially_paid,paid',
            'criteria.source'     => 'nullable|in:legacy_import,manual,fee_engine',
            'discount_type'       => 'required|in:percent,fixed',
            'discount_value'      => 'required|numeric|min:0.01',
            'discount_reason'     => 'nullable|string|max:255',
            'dry_run'             => 'boolean',
        ];
    }
}
