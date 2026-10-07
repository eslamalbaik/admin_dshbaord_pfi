<?php

namespace App\Http\Requests\Contractor;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Contractor;
use App\Support\UploadLimits;

class UpdateFullProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $contractorId = $this->user('contractor')->id;

        // نفس قاعدة لوحة الأدمن: pdf/doc/docx/jpg/jpeg/png وحتى 12 ميجابايت (مقيّدة بحد PHP).
        $fileRule = UploadLimits::documentRule();

        return [
            // حقول مقفلة عن قصد ولا تظهر هنا إطلاقاً — تعديلها صلاحية لوحة الأدمن فقط:
            // name, membership_number, commercial_register, owner_name, authorized_person,
            // authorized_person_id_number, authorized_person_phone, authorized_person_whatsapp,
            // partners, specialties, classification.
            //
            // ‼ classification و specialties تحديداً محذوفان لأنهما مُدخَلا
            // MembershipFeeCalculator نفسه (يقرأ $contractor->specialties) ويغذّيان توليد
            // الشهادات، فكان قبولهما هنا يعني أن المقاول يستطيع من التطبيق تخفيض تصنيفه
            // بنفسه فيُخفّض الرسم المحتسَب عليه، وتغيير الدرجة المطبوعة على شهادته — بلا
            // مراجعة ولا أثر. إعادتهما هنا تُعيد فتح الثغرة.
            'established_date'              => 'nullable|date',
            'email'                         => 'nullable|email|unique:contractors,email,' . $contractorId,
            'phone'                         => 'nullable|string|max:20|unique:contractors,phone,' . $contractorId,
            'governorate_id'                => 'nullable|integer|exists:governorates,id',
            'city_id'                       => 'nullable|integer|exists:cities,id',
            'address'                       => 'nullable|string',
            'notes'                         => 'nullable|string',

            // Text fields
            'fax'                           => 'nullable|string|max:50',
            'district'                      => 'nullable|string|max:100',
            'building'                      => 'nullable|string|max:100',
            'floor'                         => 'nullable|string|max:50',
            'capital'                       => 'nullable|string|max:100',
            'registration_date'             => 'nullable|date',
            'legal_form'                    => 'nullable|string|max:100',
            'company_purposes'              => 'nullable|string',
            // فريد مثل لوحة الأدمن — بدونه كان الرقم المكرر يصل لقيد الـ unique في قاعدة
            // البيانات فيرجع التطبيق "حدث خطأ غير متوقع" (500) بدل رسالة واضحة.
            'license_number'                => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('contractors', 'license_number')->whereNull('deleted_at')->ignore($contractorId),
            ],

            // Files — كل المستندات قابلة للرفع/الاستبدال المباشر، بما فيها partners_ids رغم
            // أن حقل partners النصي نفسه مقفل.
            'cr_file'                       => $fileRule,
            'id_file'                       => $fileRule,
            'lease_or_ownership_contract'   => $fileRule,
            'company_approval_letter'       => $fileRule,
            'municipal_license'             => $fileRule,
            'company_register'              => $fileRule,
            'articles_of_association'       => $fileRule,
            'internal_bylaws'               => $fileRule,
            'bank_dealing_letter'           => $fileRule,
            'secretary_contract'            => $fileRule,
            'full_time_engineer_certificate'=> $fileRule,
            'accountant_certificate_or_contract' => $fileRule,
            'partners_ids'                  => $fileRule,
            'authorization_letter'          => $fileRule,
            'authorized_signature'          => $fileRule,
        ];
    }

    public function attributes(): array
    {
        return array_merge(Contractor::DOCUMENT_LABELS, [
            'license_number' => 'رقم رخصة البلدية',
        ]);
    }

    public function messages(): array
    {
        return UploadLimits::documentMessages(Contractor::DOCUMENT_LABELS);
    }
}
