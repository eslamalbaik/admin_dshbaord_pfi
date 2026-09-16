<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\OtpCooldownException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contractor\ChangePasswordRequest;
use App\Http\Requests\Contractor\LoginRequest;
use App\Http\Requests\Contractor\RequestPhoneChangeOtpRequest;
use App\Http\Requests\Contractor\UpdateFullProfileRequest;
use App\Http\Requests\Contractor\UpdateProfileRequest;
use App\Http\Requests\Contractor\VerifyPhoneChangeOtpRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Contractor;
use App\Services\ContractorFileService;
use App\Services\ContractorProfilePdfService;
use App\Services\ContractorProfileService;
use App\Services\OtpService;
use App\Support\ApiMessages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ContractorAuthController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private ContractorProfileService    $profileService,
        private ContractorFileService       $fileService,
        private OtpService                  $otpService,
        private ContractorProfilePdfService $pdfService,
    ) {
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  POST /api/contractor/auth/login
    //  الحقول: identifier (email أو phone) + password + fcm_token (اختياري)
    // ─────────────────────────────────────────────────────────────────────────
    public function login(LoginRequest $request)
    {
        $contractor = Contractor::where('membership_number', trim($request->membership_number))->first();

        // التحقق من العضوية أولاً (REQ-05): غير موجود = رسالة عضوية واضحة، وليس خطأ اعتماديات
        if (! $contractor) {
            return $this->error(ApiMessages::NO_MEMBERSHIP, 404, null, 'no_membership');
        }

        if (! Hash::check($request->password, $contractor->password)) {
            return $this->error(ApiMessages::INVALID_CREDENTIALS, 401, null, 'invalid_credentials');
        }

        // شروط الحظر — مصدر واحد في الـ Model
        $eligibility = $contractor->loginEligibility();
        if (! $eligibility['eligible']) {
            return $this->error($eligibility['reason'], $eligibility['http_code'], null, $eligibility['error_key']);
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

        // تذكير بإكمال الملف الشخصي — مرة واحدة فقط طالما التذكير السابق لم يُقرأ بعد
        if (! $contractor->profile_data_complete) {
            $hasUnreadReminder = $contractor->unreadNotifications()
                ->where('type', \App\Notifications\CompleteProfileNotification::class)
                ->exists();

            if (! $hasUnreadReminder) {
                $contractor->notify(new \App\Notifications\CompleteProfileNotification());
            }
        }

        // استجابة خفيفة (REQ-02): البيانات الأساسية فقط — التفاصيل والملفات عبر Get Profile
        return $this->successWithToken(
            $token->plainTextToken,
            $this->profileService->liteResource($contractor),
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
    //  GET /api/v1/contractor/auth/profile
    //  نفس الملف الشخصي الكامل — مُوحّد بمسارين للتوافق مع العملاء الحاليين (REQ-03)
    // ─────────────────────────────────────────────────────────────────────────
    public function profile(Request $request)
    {
        $contractor = $request->user('contractor');
        $contractor->load([
            'activeMembership', 'governorate', 'cityModel',
            'equipment' => fn($q) => $q->where('status', 'visible'),
            'activeEquipmentSubscription.package',
        ]);

        return $this->success($this->profileService->fullResource($contractor), 'تم جلب الملف الشخصي بنجاح');
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  POST /api/v1/contractor/auth/logo
    //  رفع/استبدال شعار الشركة
    // ─────────────────────────────────────────────────────────────────────────
    public function updateLogo(Request $request)
    {
        $contractor = $request->user('contractor');

        $request->validate([
            'logo' => 'required|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        $path = $this->fileService->uploadLogo($request->file('logo'), $contractor);
        $contractor->update(['logo' => $path]);

        return $this->success($this->profileService->fullResource($contractor), 'تم تحديث شعار الشركة بنجاح.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  PATCH /api/contractor/auth/profile
    //  تعديل بيانات المقاول (الاسم، الهاتف، المحافظة والمدينة بالـ ID)
    // ─────────────────────────────────────────────────────────────────────────
    public function updateProfile(UpdateProfileRequest $request)
    {
        $contractor = $request->user('contractor');
        $validated = $request->validated();

        $this->profileService->applyLocation($validated, $contractor);
        $contractor->update($validated);

        return $this->success($this->profileService->fullResource($contractor), 'تم تحديث الملف الشخصي بنجاح.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  POST /api/v1/contractor/auth/profile/phone/request-otp
    //  الخطوة 1: طلب رمز تحقق لرقم الجوال الجديد قبل تعديل الملف الشخصي
    // ─────────────────────────────────────────────────────────────────────────
    public function requestPhoneChangeOtp(RequestPhoneChangeOtpRequest $request)
    {
        $contractor = $request->user('contractor');
        $newPhone = trim($request->phone);

        if ($newPhone === $contractor->phone) {
            return $this->error('رقم الجوال المُدخل هو نفس رقمك الحالي.', 422, null, 'same_phone');
        }

        if (Contractor::where('phone', $newPhone)->where('id', '!=', $contractor->id)->exists()) {
            return $this->error('رقم الجوال هذا مستخدَم بحساب آخر.', 422, null, 'phone_taken');
        }

        try {
            $result = $this->otpService->sendOtp(
                'profile_phone',
                $contractor->id,
                $newPhone,
                'اتحاد المقاولين: رمز تحقق تغيير رقم الجوال: {otp}. صالح لمدة 10 دقائق.',
            );
        } catch (OtpCooldownException $e) {
            return $this->error($e->getMessage(), 429, ['expires_in' => $e->expiresIn], 'otp_cooldown');
        }

        $data = ['expires_in' => $result['expires_in']];
        if ($result['is_preview']) {
            $data['otp_preview'] = $result['otp'];
        }

        return $this->success($data, 'تم إرسال رمز التحقق إلى رقم الجوال الجديد.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  POST /api/v1/contractor/auth/profile/phone/verify-otp
    //  الخطوة 2: تأكيد الرمز — يُتيح إرسال نفس الرقم بـ profile/update بعدها
    // ─────────────────────────────────────────────────────────────────────────
    public function verifyPhoneChangeOtp(VerifyPhoneChangeOtpRequest $request)
    {
        $contractor = $request->user('contractor');

        try {
            $cached = $this->otpService->verifyOtp('profile_phone', $contractor->id, $request->otp);
        } catch (ValidationException $e) {
            return $this->error('رمز التحقق غير صحيح أو انتهت صلاحيته.', 422, null, 'invalid_otp');
        }

        // صالح 15 دقيقة — كافية لإكمال حفظ باقي التعديلات بنفس الشاشة
        $this->otpService->storeVerifiedResult('profile_phone', $contractor->id, $cached['phone']);

        return $this->success(['phone' => $cached['phone']], 'تم التحقق من رقم الجوال بنجاح.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  POST /api/v1/contractor/auth/profile/update
    //  تحديث الملف الشخصي الكامل مع الملفات المرفقة
    // ─────────────────────────────────────────────────────────────────────────
    public function updateFullProfile(UpdateFullProfileRequest $request)
    {
        $contractor = $request->user('contractor');
        if (! $contractor) {
            return $this->error('غير مصرح بالوصول.', 401);
        }

        $validated = $request->validated();

        // تغيير رقم الجوال يتطلب تحقق OTP مسبق (phone/request-otp + phone/verify-otp)
        // — لا يُقبل مباشرة ولو مختلف عن القيمة المخزَّنة، لأن الجوال يُستخدم لتفعيل/دخول الحساب.
        if (array_key_exists('phone', $validated) && $validated['phone'] && $validated['phone'] !== $contractor->phone) {
            $verifiedPhone = $this->otpService->getVerifiedResult('profile_phone', $contractor->id);

            if ($verifiedPhone !== $validated['phone']) {
                return $this->error(
                    'يجب التحقق من رقم الجوال الجديد أولاً عبر رمز التحقق (OTP) قبل حفظ التعديل.',
                    422,
                    null,
                    'phone_verification_required',
                );
            }

            $this->otpService->forgetVerifiedResult('profile_phone', $contractor->id);
            $validated['phone_verified_at'] = now();
        }

        $this->profileService->applyLocation($validated, $contractor);
        $this->fileService->uploadProfileFiles($request, $validated, $contractor);

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

        return $this->success($this->profileService->fullResource($contractor), 'تم تحديث الملف الشخصي والملفات المرفقة بنجاح.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  POST /api/contractor/auth/change-password
    // ─────────────────────────────────────────────────────────────────────────
    public function changePassword(ChangePasswordRequest $request)
    {
        $contractor = $request->user('contractor');

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
            $this->profileService->fullResource($contractor),
            'تم تغيير كلمة المرور بنجاح.',
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  GET /api/v1/contractor/profile/download-file/{field}
    // ─────────────────────────────────────────────────────────────────────────
    public function downloadFile(Request $request, $field)
    {
        $contractor = $request->user('contractor');
        if (! $contractor) {
            return $this->error('غير مصرح بالوصول.', 401);
        }

        if (! in_array($field, $this->fileService->allowedDownloadFields())) {
            return $this->error('حقل غير صالح للاسترجاع.', 400);
        }

        if (empty($contractor->{$field})) {
            return $this->error('الملف المطلوب غير موجود أو لم يتم رفعه بعد.', 404);
        }

        $signedUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'contractor.download-file.signed',
            now()->addMinutes(15),
            ['contractor' => $contractor->id, 'field' => $field]
        );

        return $this->success(['signed_url' => $signedUrl], 'تم توليد رابط التحميل بأمان.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  GET /api/v1/downloads/contractor/{contractor}/file/{field} (SIGNED)
    // ─────────────────────────────────────────────────────────────────────────
    public function downloadFileSigned(Request $request, Contractor $contractor, $field)
    {
        if (! $request->hasValidSignature()) {
            abort(401, 'الرابط غير صالح أو انتهت صلاحيته.');
        }

        if (! in_array($field, $this->fileService->allowedDownloadFields())) {
            abort(400, 'حقل غير صالح للاسترجاع.');
        }

        $response = $this->fileService->downloadFile($contractor, $field);

        if (! $response) {
            abort(404, 'الملف المطلوب غير موجود أو لم يتم رفعه بعد.');
        }

        return $response;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  GET /api/v1/contractor/profile/pdf
    // ─────────────────────────────────────────────────────────────────────────
    public function exportPdf(Request $request)
    {
        $contractor = $request->user('contractor');
        if (! $contractor) {
            return $this->error('غير مصرح بالوصول.', 401);
        }

        $output = $this->pdfService->generate($contractor);
        $filename = "ملف_الشركة_{$contractor->membership_number}.pdf";

        return response($output, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . urlencode($filename) . '"',
        ]);
    }
}
