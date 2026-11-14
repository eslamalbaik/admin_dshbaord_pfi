<?php

namespace App\Http\Requests\Certificate;

use Illuminate\Foundation\Http\FormRequest;

class RegenerateCertificateRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'address'         => 'nullable|string|max:255',
            // النسخة الإنجليزية: العنوان بالإنجليزي، و include_english (فاضي = تلقائي إذا للمقاول اسم إنجليزي)
            'address_en'      => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9 .,&\'()\/\-#]+$/'],
            'include_english' => 'nullable|boolean',
            'decision_number' => 'nullable|string|max:100',
            'decision_date'   => 'nullable|date',
            'valid_until'     => 'nullable|date', // تاريخ انتهاء الشهادة (سارية حتى) — فاضي = آخر عضوية للمقاول
            'reason'          => 'nullable|string|max:500', // سبب التعديل — سجل المحددات الهامة
        ];
    }
}
