<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Contractor;
use App\Support\ApiMessages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;

class ContractorRegisterController extends Controller
{
    use ApiResponseTrait;

    // ─────────────────────────────────────────────────────────────────────────
    //  POST /api/v1/contractor/auth/verify-identity
    // ─────────────────────────────────────────────────────────────────────────
    public function verifyIdentity(Request $request)
    {
        $request->validate([
            'membership_number'   => 'required|string',
            'commercial_register' => 'required|string',
        ]);

        $contractor = Contractor::where('membership_number', trim($request->membership_number))
            ->where('commercial_register', trim($request->commercial_register))
            ->first();

        if (! $contractor) {
            return $this->error(
                ApiMessages::NO_MEMBERSHIP,
                404, null, 'contractor_not_found',
            );
        }

        if ($contractor->is_frozen || $contractor->status === 'suspended') {
            return $this->error('حسابك موقوف. تواصل مع الاتحاد لمزيد من المعلومات.', 403, null, 'account_inactive');
        }

        return $this->success([
            'id'                  => $contractor->id,
            'name'                => $contractor->name,
            'authorized_person'   => $contractor->authorized_person,
            'phone'               => $contractor->phone,
            'commercial_register' => $contractor->commercial_register,
            'membership_number'   => $contractor->membership_number,
            'has_password'        => !empty($contractor->password),
        ], 'تم التحقق من هويتك بنجاح.');
    }



    // ─────────────────────────────────────────────────────────────────────────
    //  POST /api/v1/contractor/auth/set-password
    // ─────────────────────────────────────────────────────────────────────────
    public function setPassword(Request $request)
    {
        $request->validate([
            'membership_number'   => 'required|string',
            'commercial_register' => 'required|string',
            'password'            => 'required|string|min:8',
            'password_confirmation'=> 'nullable|string',
        ]);

        $contractor = Contractor::where('membership_number', trim($request->membership_number))
            ->where('commercial_register', trim($request->commercial_register))
            ->first();

        if (! $contractor) {
            return $this->error(ApiMessages::NO_MEMBERSHIP, 404, null, 'contractor_not_found');
        }

        if ($contractor->is_frozen || $contractor->status === 'suspended') {
            return $this->error('حسابك موقوف. تواصل مع الاتحاد لمزيد من المعلومات.', 403, null, 'account_inactive');
        }

        if ($contractor->password) {
            // مسجّل مسبقاً — نتحقق من كلمة المرور
            if (! Hash::check($request->password, $contractor->password)) {
                return $this->error('كلمة المرور غير صحيحة.', 401, null, 'invalid_credentials');
            }

            // إذا لم يفعّل جواله بعد، نعيد إرسال رمز التحقق بدل تسجيل الدخول
            if (! $contractor->phone_verified_at) {
                return $this->sendRegistrationOtp($contractor);
            }
        } else {
            // إذا لم يكن لديه كلمة مرور، نتحقق من تأكيد كلمة المرور ونقوم بحفظها
            if ($request->password !== $request->password_confirmation) {
                return $this->error('تأكيد كلمة المرور غير متطابق.', 422, null, 'password_mismatch');
            }
            $contractor->update([
                'password'          => Hash::make($request->password),
                'profile_completed' => true,
            ]);

            // التسجيل الجديد يتطلب تفعيل رقم الجوال قبل الدخول
            return $this->sendRegistrationOtp($contractor);
        }

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
                'trade'               => $contractor->trade,
                'classification'      => $contractor->classification,
            ],
            'تم تسجيل الدخول بنجاح.',
            200,
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  POST /api/v1/contractor/auth/verify-otp
    //  تفعيل رقم الجوال بعد التسجيل — بعد النجاح يعود المستخدم لشاشة تسجيل الدخول
    // ─────────────────────────────────────────────────────────────────────────
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'membership_number'   => 'required|string',
            'commercial_register' => 'required|string',
            'otp'                 => 'required|string',
        ]);

        $contractor = Contractor::where('membership_number', trim($request->membership_number))
            ->where('commercial_register', trim($request->commercial_register))
            ->first();

        if (! $contractor) {
            return $this->error(ApiMessages::NO_MEMBERSHIP, 404, null, 'contractor_not_found');
        }

        if ($contractor->is_frozen || $contractor->status === 'suspended') {
            return $this->error('حسابك موقوف. تواصل مع الاتحاد لمزيد من المعلومات.', 403, null, 'account_inactive');
        }

        if ($contractor->phone_verified_at) {
            return $this->success([
                'membership_number' => $contractor->membership_number,
                'phone_verified'    => true,
            ], 'حسابك مفعّل مسبقاً. يمكنك تسجيل الدخول.');
        }

        $cachedOtp = Cache::get('register_otp_' . $contractor->id);

        if (! $cachedOtp || (string) $cachedOtp !== (string) $request->otp) {
            return $this->error('رمز التحقق غير صحيح أو انتهت صلاحيته.', 422, null, 'invalid_otp');
        }

        $contractor->update(['phone_verified_at' => now()]);
        Cache::forget('register_otp_' . $contractor->id);

        return $this->success([
            'membership_number' => $contractor->membership_number,
            'phone_verified'    => true,
        ], 'تم تفعيل حسابك بنجاح. يمكنك الآن تسجيل الدخول.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  POST /api/v1/contractor/auth/resend-otp
    // ─────────────────────────────────────────────────────────────────────────
    public function resendOtp(Request $request)
    {
        $request->validate([
            'membership_number'   => 'required|string',
            'commercial_register' => 'required|string',
        ]);

        $contractor = Contractor::where('membership_number', trim($request->membership_number))
            ->where('commercial_register', trim($request->commercial_register))
            ->first();

        if (! $contractor) {
            return $this->error(ApiMessages::NO_MEMBERSHIP, 404, null, 'contractor_not_found');
        }

        if ($contractor->is_frozen || $contractor->status === 'suspended') {
            return $this->error('حسابك موقوف. تواصل مع الاتحاد لمزيد من المعلومات.', 403, null, 'account_inactive');
        }

        if ($contractor->phone_verified_at) {
            return $this->error('حسابك مفعّل مسبقاً. يمكنك تسجيل الدخول.', 422, null, 'already_verified');
        }

        return $this->sendRegistrationOtp($contractor);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  توليد رمز تحقق التسجيل وإرساله (محاكاة عبر الـ Logs حالياً)
    // ─────────────────────────────────────────────────────────────────────────
    private function sendRegistrationOtp(Contractor $contractor)
    {
        $otp = mt_rand(100000, 999999);

        Cache::put('register_otp_' . $contractor->id, $otp, now()->addMinutes(10));

        // تسجيل الرمز في الـ Logs لمحاكاة الإرسال — يُستبدل بمزوّد SMS قبل الإطلاق
        Log::info("Registration OTP for contractor ID {$contractor->id} (Membership: {$contractor->membership_number}): {$otp}");

        return $this->success([
            'id'                => $contractor->id,
            'membership_number' => $contractor->membership_number,
            'phone'             => $contractor->phone,
            'otp_required'      => true,
            'otp_preview'       => $otp, // للتجربة فقط — يُحذف عند ربط مزوّد SMS حقيقي
        ], 'تم إرسال رمز التحقق إلى رقم الجوال المسجل. فعّل حسابك ثم سجّل الدخول.');
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

        // توليد رمز تحقق عشوائي من 6 أرقام
        $otp = mt_rand(100000, 999999);

        // تخزينه في الـ Cache لمدة 10 دقائق
        Cache::put('otp_' . $contractor->id, $otp, now()->addMinutes(10));

        // تسجيل الرمز في الـ Logs لمحاكاة الإرسال
        Log::info("Forgot Password OTP for contractor ID {$contractor->id} (Membership: {$contractor->membership_number}): {$otp}");

        return $this->success([
            'id'                  => $contractor->id,
            'membership_number'   => $contractor->membership_number,
            'phone'               => $contractor->phone,
            'otp_preview'         => $otp, // إرجاع الرمز للتسهيل على مطور الموبايل في الفحص والتجربة
        ], 'تم إرسال رمز التحقق إلى رقم الجوال المسجل بنجاح.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  POST /api/v1/contractor/auth/forgot-password/reset
    // ─────────────────────────────────────────────────────────────────────────
    public function forgotPasswordReset(Request $request)
    {
        $request->validate([
            'phone'    => 'required|string',
            'otp'      => 'required|string',
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

        $cachedOtp = Cache::get('otp_' . $contractor->id);

        if (!$cachedOtp || (string)$cachedOtp !== (string)$request->otp) {
            return $this->error('رمز التحقق غير صحيح أو انتهت صلاحيته.', 422, null, 'invalid_otp');
        }

        // تحديث كلمة المرور — رمز التحقق يثبت ملكية الجوال فنعتبره مفعّلاً
        $contractor->update([
            'password'          => Hash::make($request->password),
            'phone_verified_at' => $contractor->phone_verified_at ?? now(),
        ]);

        // مسح رمز التحقق من الكاش
        Cache::forget('otp_' . $contractor->id);

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
                'trade'               => $contractor->trade,
                'classification'      => $contractor->classification,
            ],
            'تم استعادة وتغيير كلمة المرور بنجاح وتسجيل دخولك.',
            200
        );
    }
}
