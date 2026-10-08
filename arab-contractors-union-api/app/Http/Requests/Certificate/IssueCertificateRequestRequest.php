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
            // اختياري: بدونه شهادة العضوية بتتولّد تلقائياً (CertificateRequestController::issue)
            'certificate' => 'nullable|file|mimes:pdf|max:10240', // 10 MB
            // بيانات الشهادة المولَّدة تلقائياً — فارغها بيرجع لقيم الموافقة ثم لملف المقاول
            'address'         => 'nullable|string|max:255',
            'decision_number' => 'nullable|string|max:100',
            'decision_date'   => 'nullable|date',
            'reason'      => 'nullable|string|max:500', // سبب التعديل — سجل المحددات الهامة
        ];
    }
}
