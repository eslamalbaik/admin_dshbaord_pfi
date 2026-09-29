<?php

namespace App\Http\Requests\Contractor;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFullProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $contractorId = $this->user('contractor')->id;

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
            'license_number'                => 'nullable|string|max:100',

            // Files — كل المستندات قابلة للرفع/الاستبدال المباشر، بما فيها partners_ids رغم
            // أن حقل partners النصي نفسه مقفل.
            'cr_file'                       => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'id_file'                       => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'lease_or_ownership_contract'   => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'company_approval_letter'       => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'municipal_license'             => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'company_register'              => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'articles_of_association'       => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'internal_bylaws'               => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'bank_dealing_letter'           => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'secretary_contract'            => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'full_time_engineer_certificate'=> 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'accountant_certificate_or_contract' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'partners_ids'                  => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'authorization_letter'          => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'authorized_signature'          => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ];
    }
}
