<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contractor\ChangePasswordRequest;
use App\Http\Requests\Contractor\LoginRequest;
use App\Http\Requests\Contractor\UpdateFullProfileRequest;
use App\Http\Requests\Contractor\UpdateProfileRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Contractor;
use App\Models\User;
use App\Notifications\ContractorProfileUpdatedNotification;
use App\Services\ContractorFileService;
use App\Services\ContractorProfilePdfService;
use App\Services\ContractorProfileService;
use App\Services\ProfileChangeService;
use App\Support\ProfileReview;
use App\Support\ApiMessages;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class ContractorAuthController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private ContractorProfileService    $profileService,
        private ContractorFileService       $fileService,
        private ContractorProfilePdfService $pdfService,
        private ProfileChangeService        $profileChanges,
    ) {
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  POST /api/contractor/auth/login
    //  الحقول: identifier (رقم العضوية أو جوال المفوض بالتوقيع) + password + fcm_token (اختياري)
    //  جوال المفوض من أي دولة مفعّلة بالإعدادات (+970 / +962 / +20 …) — GET /api/v1/app/country-codes
    //  membership_number مقبول كاسم قديم لنفس الحقل (توافق مع نسخ التطبيق الحالية)
    // ─────────────────────────────────────────────────────────────────────────
    public function login(LoginRequest $request)
    {
        $identifier = trim((string) ($request->identifier ?? $request->membership_number));

        $contractor = Contractor::where('membership_number', \App\Rules\MembershipNumber::normalize($identifier))->first();

        if (! $contractor && \App\Support\PhoneNumber::looksLikeMobile($identifier)) {
            // جوال الحساب أو جوال المفوض (نفس اللي بتدوّر فيه استعادة كلمة السر). ممكن نفس الرقم
            // يكون لأكثر من شركة، فبنحدد الشركة بكلمة السر؛ إذا انطبقت على أكثر من شركة بنطلب رقم العضوية.
            $candidates = Contractor::loginPhoneCandidates($identifier);

            if ($candidates->isEmpty()) {
                return $this->error(ApiMessages::AUTHORIZED_PHONE_NOT_FOUND, 404, null, 'authorized_phone_not_found');
            }

            $matches = $candidates->filter(fn (Contractor $c) => $c->password && Hash::check($request->password, $c->password));

            if ($matches->count() > 1) {
                return $this->error(ApiMessages::AUTHORIZED_PHONE_AMBIGUOUS, 409, null, 'authorized_phone_ambiguous');
            }
            if ($matches->isEmpty()) {
                return $this->error(ApiMessages::INVALID_CREDENTIALS, 401, null, 'invalid_credentials');
            }

            $contractor = $matches->first();
        }

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
            'fcm_token'     => $request->fcm_token ?: $contractor->fcm_token,
        ]);

        // تذكير بإكمال الملف التعريفي — مرة واحدة فقط طالما التذكير السابق لم يُقرأ بعد
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
    //  نفس الملف التعريفي الكامل — مُوحّد بمسارين للتوافق مع العملاء الحاليين (REQ-03)
    // ─────────────────────────────────────────────────────────────────────────
    public function profile(Request $request)
    {
        $contractor = $request->user('contractor');
        $contractor->load([
            'activeMembership', 'governorate', 'cityModel',
            'equipment' => fn($q) => $q->where('status', 'visible'),
            'activeEquipmentSubscription.package',
        ]);

        return $this->success(
            $this->profileService->fullResource($contractor),
            'تم جلب الملف التعريفي بنجاح',
        );
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
    //  تعديل بيانات المقاول (الاسم، المحافظة والمدينة بالـ ID) — الجوال لا يُغيَّر من هنا
    // ─────────────────────────────────────────────────────────────────────────
    public function updateProfile(UpdateProfileRequest $request)
    {
        $contractor = $request->user('contractor');

        if ($this->attemptsPhoneChange($request, $contractor)) {
            return $this->phoneChangeNotAllowed();
        }

        $validated = $request->validated();
        unset($validated['phone']);

        // توكن الإشعارات خارج المراجعة دائماً — لو دخلها بتوقف إشعارات المقاول
        if (array_key_exists('fcm_token', $validated)) {
            $contractor->update(['fcm_token' => $validated['fcm_token']]);
            unset($validated['fcm_token']);
        }

        $before = $contractor->only(array_keys($validated));

        $this->profileService->applyLocation($validated, $contractor);

        if (ProfileReview::enabled()) {
            $result = $this->profileChanges->submit($contractor, $validated, $request);

            return $this->reviewResponse($contractor, $result);
        }

        $contractor->update($validated);

        $this->notifyAdminsOfProfileChange($contractor, $before, $validated);

        return $this->success($this->profileService->fullResource($contractor), 'تم تحديث الملف التعريفي بنجاح.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  POST /api/v1/contractor/auth/profile/phone/request-otp
    //  POST /api/v1/contractor/auth/profile/phone/verify-otp
    //  رقم الجوال لا يُغيَّر من التطبيق — من لوحة الإدارة فقط (قرار المالك 2026-11-05،
    //  يلغي التعديل المباشر المعتمد 2026-10-13). المساران باقيان حتى تحصل نسخ التطبيق
    //  القديمة على رسالة واضحة بدل 404.
    // ─────────────────────────────────────────────────────────────────────────
    public function requestPhoneChangeOtp()
    {
        return $this->phoneChangeNotAllowed();
    }

    public function verifyPhoneChangeOtp()
    {
        return $this->phoneChangeNotAllowed();
    }

    private function phoneChangeNotAllowed()
    {
        return $this->error(
            'لا يمكن تغيير رقم الجوال من التطبيق. لتغييره تواصل مع الاتحاد.',
            403,
            null,
            'phone_change_not_allowed',
        );
    }

    /**
     * التطبيق يعيد إرسال الرقم الحالي مع كل حفظ للملف — يُقبل ويُتجاهل، وكذلك الفراغ. رقم
     * مختلف يُرفض. "الحالي" = جوال الحساب أو جوال المفوض بعد توحيد الصيغة.
     */
    private function attemptsPhoneChange(Request $request, Contractor $contractor): bool
    {
        $sent = PhoneNumber::normalize(is_string($request->input('phone')) ? $request->input('phone') : null);

        if (! $sent) {
            return false;
        }

        $current = array_filter([
            PhoneNumber::normalize($contractor->phone),
            PhoneNumber::normalize($contractor->authorized_person_phone),
        ]);

        return ! in_array($sent, $current, true);
    }

    /**
     * تسجيل أي محاولة لتعديل حقلَي الرسوم من بوابة المقاول (TASK-17).
     *
     * classification و specialties لم يبقيا مقبولَين بـUpdateFullProfileRequest، فقيمتهما
     * تُهمَل تلقائياً ولا تصل قاعدة البيانات. لكن الإهمال الصامت يخفي المحاولة نفسها، وهي
     * الأثر الذي كان مفقوداً أصلاً: المقاول كان يستطيع تخفيض تصنيفه فيُخفّض رسمه المحتسَب،
     * ولا سجلّ يُرجَع إليه. فتُقيَّد المحاولة هنا بسجل المالية عندما تختلف القيمة المُرسَلة
     * عن المخزَّنة.
     *
     * الرفض صامت لا بـ422 عن قصد: نموذج الملف بالتطبيق يرسل حقوله كاملة في كل حفظ، فرفض
     * الطلب كان سيُعطّل كل تعديل ملف تعريفي في الإنتاج فوراً بدل أن يُغلق ثغرة واحدة.
     */
    private function rejectFeeRelevantFields(Request $request, Contractor $contractor): void
    {
        foreach (['classification', 'specialties'] as $field) {
            if (! $request->has($field)) {
                continue;
            }

            $submitted = $request->input($field);
            $current   = $field === 'specialties' ? $contractor->specialties : $contractor->classification;

            // المقارنة بالنص: specialties تصل كـJSON مُسلسَل من التطبيق
            if (json_encode($submitted) === json_encode($current)) {
                continue;
            }

            Log::channel('finance')->warning('contractor.fee_field_change_rejected', [
                'contractor_id'     => $contractor->id,
                'membership_number' => $contractor->membership_number,
                'field'             => $field,
                'submitted'         => $submitted,
                'current'           => $current,
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  POST /api/v1/contractor/auth/profile/update
    //  تحديث الملف التعريفي الكامل مع الملفات المرفقة
    // ─────────────────────────────────────────────────────────────────────────
    public function updateFullProfile(UpdateFullProfileRequest $request)
    {
        $contractor = $request->user('contractor');
        if (! $contractor) {
            return $this->error('غير مصرح بالوصول.', 401);
        }

        if ($this->attemptsPhoneChange($request, $contractor)) {
            return $this->phoneChangeNotAllowed();
        }

        $validated = $request->validated();

        // نسخ التطبيق التي لم تكن تستلم established_date بالملف تُرسله فارغاً في كل حفظ،
        // فكان يمسح التاريخ الذي أدخلته الإدارة. الفراغ هنا لا يعني "احذف التاريخ".
        if (array_key_exists('established_date', $validated) && empty($validated['established_date'])) {
            unset($validated['established_date']);
        }

        // رقم الجوال لا يُغيَّر من التطبيق (راجع attemptsPhoneChange) — الرقم الحالي المُعاد
        // إرساله مع الحفظ يُتجاهل.
        unset($validated['phone']);

        $this->rejectFeeRelevantFields($request, $contractor);

        // الحقول المقفلة (name, membership_number, commercial_register, owner_name,
        // authorized_person*, partners, specialties, classification) أصلاً غير موجودة في
        // UpdateFullProfileRequest::rules() فلا تصل هنا إطلاقاً. الباقي يُكتب مباشرة على
        // contractors فور التحقق، والإدارة تُبلَّغ بما تغيّر بدل انتظار موافقتها المسبقة.
        $textFields = collect($validated)->reject(fn ($v, $key) => $request->hasFile($key))->toArray();
        $before = $contractor->only(array_keys($textFields));

        $this->profileService->applyLocation($validated, $contractor);

        // مراجعة الإدارة (Maker-Checker): الحقول القانونية والمستندات بتستنى الاعتماد،
        // بيانات التواصل بتنطبّق فوراً وبتنراجع بعدين — راجع ProfileChangeService
        if (ProfileReview::enabled()) {
            $text = collect($validated)->reject(fn ($v, $key) => $request->hasFile($key) || ProfileReview::isFile($key))->toArray();
            $result = $this->profileChanges->submit($contractor, $text, $request);

            return $this->reviewResponse($contractor, $result);
        }

        $this->fileService->uploadProfileFiles($request, $validated, $contractor);

        $contractor->update($validated);

        $this->notifyAdminsOfProfileChange($contractor, $before, $textFields);

        return $this->success($this->profileService->fullResource($contractor), 'تم تحديث الملف التعريفي والملفات المرفقة بنجاح.');
    }

    /**
     * رد الحفظ لما المراجعة مفعّلة: نفس شكل الملف (القيم الرسمية) + applied_fields /
     * pending_fields + profile_review، ورسالة بتقول شو انحفظ وشو راح للمراجعة. نفس كود
     * 200 عن قصد حتى نسخ التطبيق القديمة ما تنكسر.
     */
    private function reviewResponse(Contractor $contractor, array $result)
    {
        $contractor->refresh()->load(['activeMembership', 'governorate', 'cityModel']);

        return $this->success(array_merge($this->profileService->fullResource($contractor), [
            'pending_review'   => (bool) $result['pending'],
            'applied_fields'   => $result['applied'],
            'pending_fields'   => $result['pending'],
            'withdrawn_fields' => $result['withdrawn'],
        ]), $this->profileChanges->message($result));
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  GET    /api/v1/contractor/profile-changes          — تعديلاتي (المعلّق والمرفوض)
    //  DELETE /api/v1/contractor/profile-changes          — سحب كل التعديلات المعلّقة
    //  DELETE /api/v1/contractor/profile-changes/{id}     — سحب تعديل حقل واحد
    //  POST   /api/v1/contractor/profile-changes/{id}/dismiss — إخفاء ملاحظة رفض بعد قراءتها
    // ─────────────────────────────────────────────────────────────────────────
    public function profileChanges(Request $request)
    {
        return $this->success($this->profileChanges->stateFor($request->user('contractor')), 'تم جلب تعديلات الملف التعريفي.');
    }

    public function withdrawProfileChanges(Request $request, ?int $id = null)
    {
        $contractor = $request->user('contractor');
        $count = $this->profileChanges->withdraw($contractor, $id);

        if ($id && ! $count) {
            return $this->error('هذا التعديل لم يعد قيد المراجعة.', 404, null, 'profile_change_not_pending');
        }

        return $this->success($this->profileChanges->stateFor($contractor), $count ? 'تم سحب التعديل.' : 'لا توجد تعديلات معلّقة.');
    }

    public function dismissProfileChange(Request $request, int $id)
    {
        $contractor = $request->user('contractor');

        \App\Models\ProfileChange::where('contractor_id', $contractor->id)
            ->whereKey($id)
            ->whereIn('status', [\App\Models\ProfileChange::STATUS_REJECTED, \App\Models\ProfileChange::STATUS_REVERTED])
            ->update(['dismissed_at' => now()]);

        return $this->success($this->profileChanges->stateFor($contractor), 'تم.');
    }

    /**
     * يقارن القيم قبل/بعد التحديث ويُبلّغ الإدارة بالحقول التي تغيّرت فعلاً — لا يُرسَل
     * شيء إن لم يتغيّر شيء (تحديث أعاد نفس القيم مثلاً).
     */
    private function notifyAdminsOfProfileChange(Contractor $contractor, array $before, array $submitted): void
    {
        $contractor->refresh();

        $changes = [];
        foreach (array_keys($submitted) as $key) {
            $old = $before[$key] ?? null;
            $new = $contractor->{$key};

            if (json_encode($old) !== json_encode($new)) {
                $changes[$key] = ['old' => $old, 'new' => $new];
            }
        }

        if (empty($changes)) {
            return;
        }

        $admins = User::dashboardRecipients('contractors.list');
        if ($admins->isNotEmpty()) {
            Notification::send($admins, new ContractorProfileUpdatedNotification($contractor, $changes));
        }
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
