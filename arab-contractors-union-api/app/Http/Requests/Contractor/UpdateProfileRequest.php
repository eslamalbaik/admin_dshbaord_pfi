<?php

namespace App\Http\Requests\Contractor;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $contractorId = $this->user('contractor')->id;

        return [
            // اسم الشركة لا يُعدَّل مباشرة — فقط عبر طلب تعديل اسم شركة تُوافق عليه الإدارة
            'phone'          => 'sometimes|string|max:20|unique:contractors,phone,' . $contractorId,
            'governorate_id' => 'nullable|integer|exists:governorates,id',
            'city_id'        => 'nullable|integer|exists:cities,id',
            'address'        => 'nullable|string',
            'fcm_token'      => 'nullable|string',
        ];
    }
}
