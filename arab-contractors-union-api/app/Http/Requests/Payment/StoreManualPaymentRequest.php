<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

/**
 * إضافة دفعة يدوياً من شاشة "سجل المدفوعات" نيابةً عن المقاول — تُسجَّل مؤكَّدة
 * مباشرة وتوزَّع على أقدم الذمم (نفس منطق DuesPaymentService).
 */
class StoreManualPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contractor_id'    => 'required|exists:contractors,id',
            'amount'           => 'required|numeric|min:0.01',
            'currency'         => 'nullable|in:JOD,ILS,USD',
            'exchange_rate'    => 'nullable|numeric|min:0.0001|max:1000',
            'method'           => 'nullable|in:cash,bank_transfer,cheque',
            'reference_number' => 'nullable|string|max:100',
            'notes'            => 'nullable|string|max:1000',
            // الحوالة البنكية لازم يرافقها إشعار — النقد والشيك اختياري
            'receipt_image'    => 'required_if:method,bank_transfer|nullable|file|mimes:png,jpg,jpeg,webp,pdf|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'receipt_image.required_if' => 'صورة الإشعار مطلوبة للحوالة البنكية.',
        ];
    }
}
