<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\OtpCooldownException;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Contractor;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ContractorRegisterController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private OtpService $otpService)
    {
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  POST /api/v1/contractor/auth/verify-identity
    //  الخطوة 1: التحقق من رقم الجوال وإرسال رمز التحقق إليه
    // ─────────────────────────────────────────────────────────────────────────
    public function verifyIdentity(Request $request)
    {
        $request->validate([
            'phone'          => 'required|string',
            'terms_accepted' => 'required|accepted',
        ]);

        $contractor = Contractor::where('phone', trim($request->phone))->first();

        if (! $contractor) {
            return $this->error('لا يوجد حساب مرتبط برقم الجوال المُدخل.', 404, null, 'contractor_not_found');
        }

        if (empty($contractor->membership_number)) {
            return $this->error('لا يوجد رقم عضوية مرتبط بحسابك. تواصل مع الاتحاد.', 422, null, 'no_membership_number');
        }

        if ($contractor->is_frozen || $contractor->status === 'suspended') {
            return $this->error('حسابك موقوف. تواصل مع الاتحاد لمزيد من المعلومات.', 403, null, 'account_inactive');
        }

        if ($contractor->password && $contractor->phone_verified_at) {
            return $this->error('حسابك مفعّل ومسجّل مسبقاً. يمكنك تسجيل الدخول.', 422, null, 'already_registered');
        }

        if (is_null($contractor->terms_accepted_at)) {
            $contractor->update(['terms_accepted_at' => now()]);
        }

        try {
            $result = $this->otpService->sendOtp(
                'register',
                $contractor->id,
                $contractor->phone,
                'رمز التحقق الخاص بك في اتحاد المقاولين الفلسطينيين: {otp}. صالح لمدة 10 دقائق.',
            );
        } catch (OtpCooldownException $e) {
            return $this->error($e->getMessage(), 429, ['expires_in' => $e->expiresIn], 'otp_cooldown');
        }

        $data = [
            'id'                  => $contractor->id,
            'name'                => $contractor->name,
            'authorized_person'   => $contractor->authorized_person,
            'phone'               => $contractor->phone,
            'commercial_register' => $contractor->commercial_register,
            'membership_number'   => $contractor->membership_number,
            'has_password'        => !empty($contractor->password),
            'otp_required'        => true,
            'expires_in'          => $result['expires_in'],
        ];

        if ($result['is_preview']) {
            $data['otp_preview'] = $result['otp'];
        }

        return $this->success($data, 'تم التحقق من رقم الجوال، تم إرسال رمز التحقق إليه.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  POST /api/v1/contractor/auth/verify-otp
    //  الخطوة 2: تفعيل رقم الجوال بعد إدخال رمز التحقق
    // ─────────────────────────────────────────────────────────────────────────
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'otp'   => 'required|string|size:6',
        ]);

        $contractor = Contractor::where('phone', trim($request->phone))->first();

        if (! $contractor) {
            return $this->error('لا يوجد حساب مرتبط برقم الجوال المُدخل.', 404, null, 'contractor_not_found');
        }

        if ($contractor->is_frozen || $contractor->status === 'suspended') {
            return $this->error('حسابك موقوف. تواصل مع الاتحاد لمزيد من المعلومات.', 403, null, 'account_inactive');
        }

        try {
            $this->otpService->verifyOtp('register', $contractor->id, $request->otp);
        } catch (ValidationException) {
            return $this->error('رمز التحقق غير صحيح أو انتهت صلاحيته.', 422, null, 'invalid_otp');
        }

        $contractor->update(['phone_verified_at' => now()]);

        return $this->success([
            'id'                  => $contractor->id,
            'name'                => $contractor->name,
            'authorized_person'   => $contractor->authorized_person,
            'commercial_register' => $contractor->commercial_register,
            'membership_number'   => $contractor->membership_number,
            'phone'               => $contractor->phone,
            'phone_verified'      => true,
            'has_password'        => !empty($contractor->password),
        ], $contractor->password
            ? 'تم تفعيل رقم جوالك بنجاح. يمكنك الآن تسجيل الدخول.'
            : 'تم تفعيل رقم جوالك بنجاح. أدخل كلمة مرور لإكمال التسجيل.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  POST /api/v1/contractor/auth/resend-otp
    // ─────────────────────────────────────────────────────────────────────────
    public function resendOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
        ]);

        $contractor = Contractor::where('phone', trim($request->phone))->first();

        if (! $contractor) {
            return $this->error('لا يوجد حساب مرتبط برقم الجوال المُدخل.', 404, null, 'contractor_not_found');
        }

        if ($contractor->is_frozen || $contractor->status === 'suspended') {
            return $this->error('حسابك موقوف. تواصل مع الاتحاد لمزيد من المعلومات.', 403, null, 'account_inactive');
        }

        if ($contractor->phone_verified_at) {
            return $this->error('رقم جوالك مفعّل مسبقاً.', 422, null, 'already_verified');
        }

        try {
            $result = $this->otpService->sendOtp(
                'register',
                $contractor->id,
                $contractor->phone,
                'رمز التحقق الخاص بك في اتحاد المقاولين الفلسطينيين: {otp}. صالح لمدة 10 دقائق.',
            );
        } catch (OtpCooldownException $e) {
            return $this->error($e->getMessage(), 429, ['expires_in' => $e->expiresIn], 'otp_cooldown');
        }

        $data = [
            'phone'        => $contractor->phone,
            'otp_required' => true,
            'expires_in'   => $result['expires_in'],
        ];

        if ($result['is_preview']) {
            $data['otp_preview'] = $result['otp'];
        }

        return $this->success($data, 'تم إرسال رمز تحقق جديد إلى رقم جوالك.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  POST /api/v1/contractor/auth/set-password
    //  الخطوة 3: تعيين كلمة المرور بعد تفعيل رقم الجوال — يُنشئ الجلسة مباشرة
    // ─────────────────────────────────────────────────────────────────────────
    public function setPassword(Request $request)
    {
        $request->validate([
            'phone'                 => 'required|string',
            'password'              => 'required|string|min:8',
            'password_confirmation' => 'required|string',
        ]);

        $contractor = Contractor::where('phone', trim($request->phone))->first();

        if (! $contractor) {
            return $this->error('لا يوجد حساب مرتبط برقم الجوال المُدخل.', 404, null, 'contractor_not_found');
        }

        if (empty($contractor->membership_number)) {
            return $this->error('لا يوجد رقم عضوية مرتبط بحسابك. تواصل مع الاتحاد.', 422, null, 'no_membership_number');
        }

        if ($contractor->is_frozen || $contractor->status === 'suspended') {
            return $this->error('حسابك موقوف. تواصل مع الاتحاد لمزيد من المعلومات.', 403, null, 'account_inactive');
        }

        if (! $contractor->phone_verified_at) {
            return $this->error('يجب تفعيل رقم الجوال أولاً عبر رمز التحقق.', 422, null, 'phone_not_verified');
        }

        if ($contractor->password) {
            return $this->error('حسابك مسجّل مسبقاً. يمكنك تسجيل الدخول.', 422, null, 'already_registered');
        }

        if ($request->password !== $request->password_confirmation) {
            return $this->error('تأكيد كلمة المرور غير متطابق.', 422, null, 'password_mismatch');
        }

        $contractor->update([
            'password'          => Hash::make($request->password),
            'profile_completed' => true,
            // فتح الحساب من التطبيق ينقل الحالة من "معلّق" إلى "نشط" تلقائياً —
            // 'expired' تبقى كما هي لأنها تخص انتهاء العضوية لا اكتمال التسجيل.
            ...($contractor->status === 'pending' ? ['status' => 'active'] : []),
        ]);

        // حذف التوكنات القديمة
        $contractor->tokens()->delete();

        $token = $contractor->createToken('mobile', ['*'], now()->addDays(60));
        $contractor->update(['last_login_at' => now()]);

        return $this->successWithToken(
            $token->plainTextToken,
            [
                'id'                  => $contractor->id,
                'membership_number'   => $contractor->membership_number,
                'commercial_register' => $contractor->commercial_register,
                'name'                => $contractor->name,
                'authorized_person'   => $contractor->authorized_person,
                'phone'               => $contractor->phone,
                'trade'               => $contractor->trade,
                'classification'      => $contractor->classification,
            ],
            'تم تسجيل حسابك وتسجيل الدخول بنجاح.',
            200,
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  POST /api/v1/contractor/auth/forgot-password/send-otp
    // ─────────────────────────────────────────────────────────────────────────
    public function forgotPasswordSendOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
        ]);

        $contractor = Contractor::where('phone', trim($request->phone))->first();

        if (! $contractor) {
            return $this->error('لا يوجد حساب مرتبط برقم الجوال المُدخل.', 404, null, 'contractor_not_found');
        }

        if ($contractor->is_frozen || $contractor->status === 'suspended') {
            return $this->error('حسابك موقوف. تواصل مع الاتحاد لمزيد من المعلومات.', 403, null, 'account_inactive');
        }

        // استعادة كلمة المرور متاحة فقط لمقاول عضويته فعّالة
        if (! $contractor->activeMembership) {
            return $this->error('لا يمكن استعادة كلمة المرور — لا توجد لديك عضوية فعّالة في الاتحاد. يرجى التواصل مع الاتحاد.', 403, null, 'membership_inactive');
        }

        try {
            $result = $this->otpService->sendOtp(
                'forgot',
                $contractor->id,
                $contractor->phone,
                'رمز استعادة كلمة المرور في اتحاد المقاولين الفلسطينيين: {otp}. صالح لمدة 10 دقائق.',
            );
        } catch (OtpCooldownException $e) {
            return $this->error($e->getMessage(), 429, ['expires_in' => $e->expiresIn], 'otp_cooldown');
        }

        $data = [
            'id'                  => $contractor->id,
            'membership_number'   => $contractor->membership_number,
            'phone'               => $contractor->phone,
            'expires_in'          => $result['expires_in'],
        ];

        if ($result['is_preview']) {
            $data['otp_preview'] = $result['otp'];
        }

        return $this->success($data, 'تم إرسال رمز التحقق إلى رقم الجوال المسجل بنجاح.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  POST /api/v1/contractor/auth/forgot-password/reset
    // ─────────────────────────────────────────────────────────────────────────
    public function forgotPasswordReset(Request $request)
    {
        $request->validate([
            'phone'    => 'required|string',
            'otp'      => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $contractor = Contractor::where('phone', trim($request->phone))->first();

        if (! $contractor) {
            return $this->error('لا يوجد حساب مرتبط برقم الجوال المُدخل.', 404, null, 'contractor_not_found');
        }

        if ($contractor->is_frozen || $contractor->status === 'suspended') {
            return $this->error('حسابك موقوف. تواصل مع الاتحاد لمزيد من المعلومات.', 403, null, 'account_inactive');
        }

        // استعادة كلمة المرور متاحة فقط لمقاول عضويته فعّالة
        if (! $contractor->activeMembership) {
            return $this->error('لا يمكن استعادة كلمة المرور — لا توجد لديك عضوية فعّالة في الاتحاد. يرجى التواصل مع الاتحاد.', 403, null, 'membership_inactive');
        }

        try {
            $this->otpService->verifyOtp('forgot', $contractor->id, $request->otp);
        } catch (ValidationException) {
            return $this->error('رمز التحقق غير صحيح أو انتهت صلاحيته.', 422, null, 'invalid_otp');
        }

        // تحديث كلمة المرور — رمز التحقق يثبت ملكية الجوال فنعتبره مفعّلاً
        $contractor->update([
            'password'          => Hash::make($request->password),
            'phone_verified_at' => $contractor->phone_verified_at ?? now(),
        ]);

        // مسح الجلسات القديمة وتوليد جلسة جديدة للمستخدم للدخول الفوري
        $contractor->tokens()->delete();
        $token = $contractor->createToken('mobile', ['*'], now()->addDays(60));
        $contractor->update(['last_login_at' => now()]);

        return $this->successWithToken(
            $token->plainTextToken,
            [
                'id'                  => $contractor->id,
                'membership_number'   => $contractor->membership_number,
                'name'                => $contractor->name,
                'authorized_person'   => $contractor->authorized_person,
                'phone'               => $contractor->phone,
                'trade'               => $contractor->trade,
                'classification'      => $contractor->classification,
            ],
            'تم استعادة وتغيير كلمة المرور بنجاح وتسجيل دخولك.',
            200
        );
    }
}
