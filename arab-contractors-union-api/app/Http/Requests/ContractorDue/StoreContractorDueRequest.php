<?php

namespace App\Http\Requests\ContractorDue;

use Illuminate\Foundation\Http\FormRequest;

class StoreContractorDueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contractor_id' => 'required|exists:contractors,id',
            'description'   => 'required|string|max:500',
            'amount_jod'    => 'required|numeric|min:0.01|max:99999999',
            'year'          => 'nullable|integer|between:1990,2100',
            'period'        => 'nullable|string|max:50',

            // تاريخ الاستحقاق لا يُسبق اليوم إلا بتفعيل allow_backdate صراحةً (TASK-17 #7).
            // الاستثناء مقصود ومطلوب: تسجيل ذمم متأخّرة قديمة حالة مشروعة، لكن يجب أن تكون
            // قراراً واعياً بسبب مكتوب لا تاريخاً مكتوباً بالخطأ.
            'due_date'      => $this->boolean('allow_backdate')
                ? 'nullable|date'
                : 'nullable|date|after_or_equal:today',
            'allow_backdate'  => 'boolean',
            'backdate_reason' => 'required_if:allow_backdate,1,true|nullable|string|max:255',

            'notes'         => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'due_date.after_or_equal' => 'تاريخ الاستحقاق لا يمكن أن يكون قبل تاريخ اليوم. لتسجيل ذمة متأخّرة سابقة، فعّل خيار «ذمة سابقة/متأخّرة» واذكر السبب.',
            'backdate_reason.required_if' => 'سبب التاريخ السابق إلزامي عند تسجيل ذمة متأخّرة.',
        ];
    }
}
