<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\City;
use App\Models\Contractor;
use App\Support\ApiMessages;
use App\Support\ContractorLookups;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mpdf\Mpdf;

class ContractorAuthController extends Controller
{
    use ApiResponseTrait;
    // ─────────────────────────────────────────────────────────────────────────
    //  POST /api/contractor/auth/login
    //  الحقول: identifier (email أو phone) + password + fcm_token (اختياري)
    // ─────────────────────────────────────────────────────────────────────────
    public function login(Request $request)
    {
        $request->validate([
            'membership_number' => 'required|string',
            'password'          => 'required|string',
            'fcm_token'         => 'nullable|string',
            'device_name'       => 'nullable|string|max:120',
        ]);

        $contractor = Contractor::where('membership_number', trim($request->membership_number))->first();

        // التحقق من العضوية أولاً (REQ-05): غير موجود = رسالة عضوية واضحة، وليس خطأ اعتماديات
        if (! $contractor) {
            return $this->error(ApiMessages::NO_MEMBERSHIP, 404, null, 'no_membership');
        }

        if (! Hash::check($request->password, $contractor->password)) {
            return $this->error(ApiMessages::INVALID_CREDENTIALS, 401, null, 'invalid_credentials');
        }

        if ($contractor->is_frozen) {
            return $this->error(ApiMessages::ACCOUNT_FROZEN, 403, null, 'account_frozen');
        }

        if ($contractor->status === 'suspended') {
            return $this->error(ApiMessages::ACCOUNT_SUSPENDED, 403, null, 'account_suspended');
        }

        // لا يُسمح بالدخول قبل تفعيل رقم الجوال (شاشة التحقق)
        if (! $contractor->phone_verified_at) {
            return $this->error(ApiMessages::PHONE_NOT_VERIFIED, 403, null, 'phone_not_verified');
        }

        // حذف التوكنات القديمة (جلسة واحدة نشطة)
        $contractor->tokens()->delete();

        $deviceLabel = $request->device_name
            ?? substr($request->header('User-Agent', 'Mobile App'), 0, 120);

        // توكن صالح 60 يوم — مناسب للموبايل
        $token = $contractor->createToken(
            $deviceLabel,
            ['*'],
            now()->addDays(60)
        );

        // تحديث آخر دخول + fcm_token
        $contractor->update([
            'last_login_at' => now(),
            'fcm_token'     => $request->fcm_token ?? $contractor->fcm_token,
        ]);

        // استجابة خفيفة (REQ-02): البيانات الأساسية فقط — التفاصيل والملفات عبر Get Profile
        return $this->successWithToken(
            $token->plainTextToken,
            $this->contractorLiteResource($contractor),
            'تم تسجيل الدخول بنجاح.',
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  POST /api/contractor/auth/logout
    // ─────────────────────────────────────────────────────────────────────────
    public function logout(Request $request)
    {
        $contractor = $request->user('contractor');

        if ($contractor) {
            $contractor->currentAccessToken()->delete();
        }

        return $this->success(message: 'تم تسجيل الخروج بنجاح.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  GET /api/contractor/auth/me
    // ─────────────────────────────────────────────────────────────────────────
    public function me(Request $request)
    {
        $contractor = $request->user('contractor');
        $contractor->load(['activeMembership', 'governorate', 'cityModel', 'equipment' => fn($q) => $q->where('status', 'visible')]);

        return $this->success($this->contractorResource($contractor));
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  GET /api/v1/contractor/auth/profile
    //  الملف الشخصي الكامل (REQ-03) — يُستدعى عند فتح شاشة الملف الشخصي فقط
    // ─────────────────────────────────────────────────────────────────────────
    public function profile(Request $request)
    {
        $contractor = $request->user('contractor');
        $contractor->load(['activeMembership', 'governorate', 'cityModel']);

        return $this->success($this->contractorResource($contractor), 'تم جلب الملف الشخصي بنجاح');
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  PATCH /api/contractor/auth/profile
    //  تعديل بيانات المقاول (الاسم، الهاتف، المحافظة والمدينة بالـ ID)
    // ─────────────────────────────────────────────────────────────────────────
    public function updateProfile(Request $request)
    {
        $contractor = $request->user('contractor');

        $validated = $request->validate([
            'name'           => 'sometimes|string|max:255',
            'phone'          => 'sometimes|string|max:20|unique:contractors,phone,' . $contractor->id,
            'governorate_id' => 'nullable|integer|exists:governorates,id',
            'city_id'        => 'nullable|integer|exists:cities,id',
            'address'        => 'nullable|string',
            'fcm_token'      => 'nullable|string',
        ]);

        $this->applyLocation($validated);

        $contractor->update($validated);

        return $this->success($this->contractorResource($contractor), 'تم تحديث الملف الشخصي بنجاح.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Private — التحقق من توافق المحافظة والمدينة ومزامنة العمود النصي القديم
    // ─────────────────────────────────────────────────────────────────────────
    private function applyLocation(array &$validated): void
    {
        if (! array_key_exists('city_id', $validated) && ! array_key_exists('governorate_id', $validated)) {
            return;
        }

        $city = ! empty($validated['city_id']) ? City::find($validated['city_id']) : null;

        if ($city) {
            // المدينة يجب أن تتبع المحافظة المُرسلة (إن أُرسلت)
            if (! empty($validated['governorate_id']) && (int) $validated['governorate_id'] !== (int) $city->governorate_id) {
                throw ValidationException::withMessages([
                    'city_id' => ['المدينة المختارة لا تتبع المحافظة المختارة.'],
                ]);
            }

            $validated['governorate_id'] = $city->governorate_id;
            // مزامنة العمود النصي القديم — يبقى الداشبورد وتصدير الـ PDF يعملان كما هما
            $validated['city'] = $city->name;
        } elseif (array_key_exists('city_id', $validated)) {
            $validated['city'] = null;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  POST /api/v1/contractor/auth/profile/update
    //  تحديث الملف الشخصي الكامل مع الملفات المرفقة
    // ─────────────────────────────────────────────────────────────────────────
    public function updateFullProfile(Request $request)
    {
        $contractor = $request->user('contractor');
        if (!$contractor) {
            return $this->error('غير مصرح بالوصول.', 401);
        }

        $validated = $request->validate([
            'name'                          => 'sometimes|string|max:255',
            'trade'                         => 'nullable|string|max:100',
            'classification'                => 'nullable|string|max:10',
            'established_year'              => 'nullable|integer|min:1900|max:' . date('Y'),
            'established_date'              => 'nullable|date',
            'specialties'                   => 'nullable|string', 
            'owner_name'                    => 'nullable|string|max:255',
            'email'                         => 'nullable|email|unique:contractors,email,' . $contractor->id,
            'phone'                         => 'nullable|string|max:20',
            'governorate_id'                => 'nullable|integer|exists:governorates,id',
            'city_id'                       => 'nullable|integer|exists:cities,id',
            'address'                       => 'nullable|string',
            'notes'                         => 'nullable|string',

            // Text fields
            'partners'                      => 'nullable|string', 
            'fax'                           => 'nullable|string|max:50',
            'building'                      => 'nullable|string|max:100',
            'floor'                         => 'nullable|string|max:50',
            'capital'                       => 'nullable|string|max:100',
            'registration_date'             => 'nullable|date',
            'legal_form'                    => 'nullable|string|max:100',
            'company_purposes'              => 'nullable|string',
            'authorized_person'             => 'nullable|string|max:255',

            // Files
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
            'partners_ids'                  => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'authorization_letter'          => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'authorized_signature'          => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $this->applyLocation($validated);
        $this->handleProfileFileUploads($request, $validated, $contractor);

        if (isset($validated['specialties']) && is_string($validated['specialties'])) {
            $validated['specialties'] = json_decode($validated['specialties'], true);
        }

        if (isset($validated['partners']) && is_string($validated['partners'])) {
            $decodedPartners = json_decode($validated['partners'], true);
            if (is_array($decodedPartners)) {
                $validated['partners'] = $decodedPartners;
            }
        }

        $contractor->update($validated);

        return $this->success($this->contractorResource($contractor), 'تم تحديث الملف الشخصي والملفات المرفقة بنجاح.');
    }

    private function handleProfileFileUploads(Request $request, &$validated, $contractor)
    {
        $fileFields = [
            'cr_file'                       => 'contractors/cr',
            'id_file'                       => 'contractors/id',
            'lease_or_ownership_contract'   => 'contractors/leases',
            'company_approval_letter'       => 'contractors/approvals',
            'municipal_license'             => 'contractors/licenses',
            'company_register'              => 'contractors/registers',
            'articles_of_association'       => 'contractors/articles',
            'internal_bylaws'               => 'contractors/bylaws',
            'bank_dealing_letter'           => 'contractors/bank_letters',
            'secretary_contract'            => 'contractors/secretary_contracts',
            'full_time_engineer_certificate'=> 'contractors/engineer_certs',
            'partners_ids'                  => 'contractors/partners_ids',
            'authorization_letter'          => 'contractors/authorization_letters',
            'authorized_signature'          => 'contractors/signatures',
        ];

        foreach ($fileFields as $field => $path) {
            if ($request->hasFile($field)) {
                if ($contractor->$field) {
                    Storage::disk('public')->delete($contractor->$field);
                }
                $validated[$field] = $request->file($field)->store($path, 'public');
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  POST /api/contractor/auth/change-password
    // ─────────────────────────────────────────────────────────────────────────
    public function changePassword(Request $request)
    {
        $contractor = $request->user('contractor');

        $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:8|confirmed',
        ]);

        if (! Hash::check($request->current_password, $contractor->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['كلمة المرور الحالية غير صحيحة.'],
            ]);
        }

        $contractor->update([
            'password' => Hash::make($request->new_password),
        ]);

        // إلغاء جميع الجلسات الأخرى بعد تغيير كلمة المرور
        $contractor->tokens()->delete();
        $newToken = $contractor->createToken('mobile', ['*'], now()->addDays(60));

        return $this->successWithToken(
            $newToken->plainTextToken,
            $this->contractorResource($contractor),
            'تم تغيير كلمة المرور بنجاح.',
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Private — الاستجابة الخفيفة (Login) — البيانات الأساسية فقط (REQ-02)
    // ─────────────────────────────────────────────────────────────────────────
    private function contractorLiteResource(Contractor $contractor): array
    {
        $membership = $contractor->activeMembership;

        return [
            'id'                   => $contractor->id,
            'membership_number'    => $contractor->membership_number,
            'commercial_register'  => $contractor->commercial_register,
            'license_number'       => $contractor->license_number,
            'name'                 => $contractor->name,
            'authorized_person'    => $contractor->authorized_person,
            'trade'                => $contractor->trade,
            'classification'       => $contractor->classification,
            'classification_label' => $contractor->classification_label,
            'email'                => $contractor->email,
            'phone'                => $contractor->phone,

            // المحافظة والمدينة كأوبجكت id + name (البيانات القديمة النصية تُرجَع بـ id = null)
            'governorate' => $contractor->governorate ? [
                'id'   => $contractor->governorate->id,
                'name' => $contractor->governorate->name,
            ] : null,
            'city' => $contractor->cityModel ? [
                'id'   => $contractor->cityModel->id,
                'name' => $contractor->cityModel->name,
            ] : ($contractor->city ? ['id' => null, 'name' => $contractor->city] : null),

            'status'               => $contractor->status,
            'is_frozen'            => $contractor->is_frozen,
            'profile_completed'    => $contractor->profile_completed,
            'last_login_at'        => $contractor->last_login_at?->toISOString(),

            'membership' => $membership ? [
                'id'         => $membership->id,
                'type'       => $membership->type,
                'status'     => $membership->status,
                'expires_at' => $membership->expires_at?->toDateString(),
                'is_expired' => $membership->expires_at?->isPast() ?? false,
            ] : null,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Private — الاستجابة الكاملة (Get Profile) — تفاصيل وملفات (REQ-03)
    // ─────────────────────────────────────────────────────────────────────────
    private function contractorResource(Contractor $contractor): array
    {
        // روابط الملفات المرفقة — حلقة واحدة بدل التكرار (REQ-08)
        $fileFields = [
            'cr_file', 'id_file', 'authorized_signature', 'lease_or_ownership_contract',
            'company_approval_letter', 'municipal_license', 'company_register',
            'articles_of_association', 'internal_bylaws', 'bank_dealing_letter',
            'secretary_contract', 'full_time_engineer_certificate', 'partners_ids',
            'authorization_letter',
        ];

        $fileUrls = [];
        foreach ($fileFields as $field) {
            $fileUrls[$field . '_url'] = $contractor->$field
                ? Storage::disk('public')->url($contractor->$field)
                : null;
        }

        return array_merge($this->contractorLiteResource($contractor), [
            'established_year'  => $contractor->established_year,
            'owner_name'        => $contractor->owner_name,
            'address'           => $contractor->address,
            'notes'             => $contractor->notes,

            // المجالات والاختصاصات والدرجات — كل مجال يضم اختصاصاته وكل اختصاص درجته
            'fields'            => ContractorLookups::buildFieldsTree($contractor->specialties),

            // Extended Fields
            'partners'          => $contractor->partners,
            'specialties'       => $contractor->specialties,
            'fax'               => $contractor->fax,
            'building'          => $contractor->building,
            'floor'             => $contractor->floor,
            'capital'           => $contractor->capital,
            'registration_date' => $contractor->registration_date,
            'legal_form'        => $contractor->legal_form,
            'company_purposes'  => $contractor->company_purposes,
        ], $fileUrls);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  GET /api/v1/contractor/profile/download-file/{field}
    // ─────────────────────────────────────────────────────────────────────────
    public function downloadFile(Request $request, $field)
    {
        $contractor = $request->user('contractor');
        if (!$contractor) {
            return $this->error('غير مصرح بالوصول.', 401);
        }

        $allowedFields = [
            'cr_file', 'id_file', 'lease_or_ownership_contract', 'company_approval_letter',
            'municipal_license', 'company_register', 'articles_of_association', 'internal_bylaws',
            'bank_dealing_letter', 'secretary_contract', 'full_time_engineer_certificate',
            'partners_ids', 'authorization_letter', 'authorized_signature'
        ];

        if (!in_array($field, $allowedFields)) {
            return $this->error('حقل غير صالح للاسترجاع.', 400);
        }

        $filePath = $contractor->$field;

        if (!$filePath || !Storage::disk('public')->exists($filePath)) {
            return $this->error('الملف المطلوب غير موجود أو لم يتم رفعه بعد.', 404);
        }

        return Storage::disk('public')->download($filePath);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  GET /api/v1/contractor/profile/pdf
    // ─────────────────────────────────────────────────────────────────────────
    public function exportPdf(Request $request)
    {
        $contractor = $request->user('contractor');
        if (!$contractor) {
            return $this->error('غير مصرح بالوصول.', 401);
        }

        $specialtiesRows = '';
        if (is_array($contractor->specialties)) {
            foreach ($contractor->specialties as $spec) {
                $fName = ContractorLookups::fieldName($spec['field_lk_type'] ?? null);
                $sName = ContractorLookups::specializationName($spec['specialization_lk_type'] ?? null);
                $grade = $spec['classification'] ?? 'غير محدد';
                $specialtiesRows .= "
                <tr>
                    <td>{$fName}</td>
                    <td>{$sName}</td>
                    <td><span class='badge-grade'>{$grade}</span></td>
                </tr>";
            }
        }

        if (empty($specialtiesRows)) {
            $specialtiesRows = '<tr><td colspan="3" style="text-align: center; color: #888;">لا يوجد اختصاصات مسجلة</td></tr>';
        }

        // تحضير الشركاء
        $partnersHtml = 'لا يوجد';
        if ($contractor->partners) {
            $partnersArr = is_array($contractor->partners) ? $contractor->partners : json_decode($contractor->partners, true);
            if (is_array($partnersArr) && !empty($partnersArr)) {
                $partnersHtml = implode('، ', $partnersArr);
            } elseif (is_string($contractor->partners)) {
                $partnersHtml = $contractor->partners;
            }
        }

        $generatedAt = now()->format('Y-m-d H:i');
        $regDate = $contractor->registration_date ? $contractor->registration_date->format('Y-m-d') : 'غير محدد';
        $estDate = $contractor->established_date ? $contractor->established_date->format('Y-m-d') : ($contractor->established_year ?? 'غير محدد');

        $html = <<<HTML
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="UTF-8">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: dejavusans; font-size: 11px; color: #2C3E50; direction: rtl; line-height: 1.6; }
  .container { padding: 10px; }
  .header { border-bottom: 3px solid #1A237E; padding-bottom: 12px; margin-bottom: 20px; }
  .header-table { width: 100%; border-collapse: collapse; }
  .header-title { font-size: 20px; color: #1A237E; font-weight: bold; text-align: right; }
  .header-subtitle { font-size: 12px; color: #D4AF37; font-weight: bold; margin-top: 5px; }
  .meta-text { font-size: 9px; color: #7F8C8D; text-align: left; }
  
  .section-title { font-size: 13px; font-weight: bold; color: #1A237E; border-right: 4px solid #D4AF37; padding-right: 8px; margin: 15px 0 10px 0; }
  
  .info-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
  .info-table td { padding: 8px 10px; border: 1px solid #E0E0E0; font-size: 11px; }
  .info-label { font-weight: bold; color: #1A237E; background-color: #F5F6FA; width: 22%; }
  .info-value { width: 28%; background-color: #FFFFFF; }
  
  .data-table { width: 100%; border-collapse: collapse; margin-top: 5px; }
  .data-table th { background-color: #1A237E; color: #FFFFFF; padding: 8px 12px; font-size: 11px; text-align: right; font-weight: bold; }
  .data-table td { padding: 8px 12px; border-bottom: 1px solid #E0E0E0; font-size: 11px; text-align: right; }
  .data-table tr:nth-child(even) td { background-color: #F8F9FD; }
  .badge-grade { background-color: #E8EAF6; color: #1A237E; padding: 2px 8px; border-radius: 4px; font-weight: bold; }
  
  .footer { margin-top: 40px; border-top: 1px solid #E0E0E0; padding-top: 10px; font-size: 9px; color: #95A5A6; text-align: center; }
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <table class="header-table">
      <tr>
        <td>
          <div class="header-title">اتحاد المقاولين الفلسطينيين</div>
          <div class="header-subtitle">ملف معلومات الشركة المعتمد</div>
        </td>
        <td style="text-align: left; vertical-align: bottom;">
          <div class="meta-text">تاريخ التصدير: {$generatedAt}</div>
          <div class="meta-text">رقم العضوية: {$contractor->membership_number}</div>
        </td>
      </tr>
    </table>
  </div>

  <div class="section-title">البيانات العامة للشركة</div>
  <table class="info-table">
    <tr>
      <td class="info-label">اسم الشركة</td>
      <td class="info-value">{$contractor->name}</td>
      <td class="info-label">رقم العضوية</td>
      <td class="info-value">{$contractor->membership_number}</td>
    </tr>
    <tr>
      <td class="info-label">رقم السجل التجاري</td>
      <td class="info-value">{$contractor->commercial_register}</td>
      <td class="info-label">رقم الترخيص</td>
      <td class="info-value">{$contractor->license_number}</td>
    </tr>
    <tr>
      <td class="info-label">المفوض بالتوقيع</td>
      <td class="info-value">{$contractor->authorized_person}</td>
      <td class="info-label">اسم المالك</td>
      <td class="info-value">{$contractor->owner_name}</td>
    </tr>
    <tr>
      <td class="info-label">رقم الجوال</td>
      <td class="info-value">{$contractor->phone}</td>
      <td class="info-label">الفاكس</td>
      <td class="info-value">{$contractor->fax}</td>
    </tr>
    <tr>
      <td class="info-label">البريد الإلكتروني</td>
      <td class="info-value">{$contractor->email}</td>
      <td class="info-label">رأس المال</td>
      <td class="info-value">{$contractor->capital}</td>
    </tr>
    <tr>
      <td class="info-label">تاريخ التسجيل</td>
      <td class="info-value">{$regDate}</td>
      <td class="info-label">تاريخ التأسيس</td>
      <td class="info-value">{$estDate}</td>
    </tr>
    <tr>
      <td class="info-label">الشكل القانوني</td>
      <td class="info-value">{$contractor->legal_form}</td>
      <td class="info-label">المدينة / العنوان</td>
      <td class="info-value">{$contractor->city} - {$contractor->address}</td>
    </tr>
    <tr>
      <td class="info-label">الشركاء</td>
      <td colspan="3" class="info-value">{$partnersHtml}</td>
    </tr>
    <tr>
      <td class="info-label">غايات الشركة</td>
      <td colspan="3" class="info-value">{$contractor->company_purposes}</td>
    </tr>
  </table>

  <div class="section-title">المجالات والاختصاصات والدرجات</div>
  <table class="data-table">
    <thead>
      <tr>
        <th>المجال الرئيسي</th>
        <th>الاختصاص التفصيلي</th>
        <th>درجة التصنيف</th>
      </tr>
    </thead>
    <tbody>
      {$specialtiesRows}
    </tbody>
  </table>

  <div class="footer">
    تم إنشاء هذا المستند إلكترونياً وصادر عن منصة اتحاد المقاولين الفلسطينيين.
  </div>
</div>
</body>
</html>
HTML;

        $tempDir = storage_path('app/mpdf-tmp');
        if (!is_dir($tempDir)) mkdir($tempDir, 0775, true);

        $mpdf = new Mpdf([
            'mode'         => 'utf-8',
            'format'       => 'A4',
            'orientation'  => 'P',
            'margin_top'   => 12,
            'margin_right' => 12,
            'margin_bottom'=> 12,
            'margin_left'  => 12,
            'tempDir'      => $tempDir,
            'default_font' => 'dejavusans',
        ]);

        $mpdf->SetDirectionality('rtl');
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont   = true;
        $mpdf->WriteHTML($html);

        $filename = "ملف_الشركة_{$contractor->membership_number}.pdf";
        $output = $mpdf->Output('', 'S');

        return response($output, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . urlencode($filename) . '"',
        ]);
    }
}
