<?php

namespace App\Services;

use App\Models\Contractor;
use App\Services\Sms\SmsSenderInterface;
use Illuminate\Support\Facades\Cache;

/**
 * خدمة OTP مركزية — تُوحِّد توليد وإرسال والتحقق من رموز التحقق.
 * مفاتيح الـ Cache تبقى متوافقة مع الشكل السابق لتجنب كسر OTPs نشطة وقت الـ deploy.
 */
class OtpService
{
    public function __construct(private SmsSenderInterface $sms)
    {
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  توليد وإرسال OTP مع حماية cooldown
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param  string  $purpose    الغرض (مثلاً: 'profile_phone')
     * @param  int     $subjectId  ID المقاول
     * @param  string  $phone      رقم الهاتف المراد إرسال الـ OTP إليه
     * @param  string  $smsMessage نص الرسالة — يُستبدل {otp} بالرمز
     * @param  int     $otpTtl     مدة صلاحية الـ OTP بالدقائق
     * @param  int     $cooldown   مدة الانتظار بين الطلبات بالثواني
     *
     * @return array{otp: int, expires_in: int, is_preview: bool}
     *
     * @throws \App\Exceptions\OtpCooldownException
     */
    public function sendOtp(
        string $purpose,
        int    $subjectId,
        string $phone,
        string $smsMessage,
        int    $otpTtl   = 10,
        int    $cooldown = 35,
    ): array {
        $cooldownKey = $this->cooldownKey($purpose, $subjectId);

        if (Cache::has($cooldownKey)) {
            $expiresIn = max(0, Cache::get($cooldownKey) - time());
            throw new \App\Exceptions\OtpCooldownException($expiresIn);
        }

        $otp = 123456; // TODO: mt_rand(100000, 999999) في الإنتاج

        Cache::put(
            $this->otpKey($purpose, $subjectId),
            ['otp' => $otp, 'phone' => $phone],
            now()->addMinutes($otpTtl),
        );

        Cache::put($cooldownKey, time() + $cooldown, now()->addSeconds($cooldown));

        $message = str_replace('{otp}', (string) $otp, $smsMessage);
        $this->sms->send($phone, $message);

        $isPreview = config('services.sms.driver', 'log') === 'log';

        return [
            'otp'        => $otp,
            'expires_in' => $cooldown,
            'is_preview' => $isPreview,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  التحقق من OTP
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @return array{phone: string}  البيانات المخزنة مع الـ OTP (مثل رقم الهاتف)
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function verifyOtp(string $purpose, int $subjectId, string $otp): array
    {
        $key    = $this->otpKey($purpose, $subjectId);
        $cached = Cache::get($key);

        if (! $cached || (string) $cached['otp'] !== (string) $otp) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'otp' => ['رمز التحقق غير صحيح أو انتهت صلاحيته.'],
            ]);
        }

        Cache::forget($key);

        return $cached;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  تخزين/جلب/مسح نتيجة التحقق (مثل رقم جوال مُحقَّق)
    // ─────────────────────────────────────────────────────────────────────────

    public function storeVerifiedResult(string $purpose, int $subjectId, mixed $value, int $minutes = 15): void
    {
        Cache::put($this->verifiedKey($purpose, $subjectId), $value, now()->addMinutes($minutes));
    }

    public function getVerifiedResult(string $purpose, int $subjectId): mixed
    {
        return Cache::get($this->verifiedKey($purpose, $subjectId));
    }

    public function forgetVerifiedResult(string $purpose, int $subjectId): void
    {
        Cache::forget($this->verifiedKey($purpose, $subjectId));
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Cache Keys — متوافقة مع الشكل الأصلي (لا تُكسر OTPs نشطة وقت deploy)
    // ─────────────────────────────────────────────────────────────────────────

    private function otpKey(string $purpose, int $subjectId): string
    {
        return "{$purpose}_otp_{$subjectId}";
    }

    private function cooldownKey(string $purpose, int $subjectId): string
    {
        return "{$purpose}_otp_cooldown_{$subjectId}";
    }

    private function verifiedKey(string $purpose, int $subjectId): string
    {
        return "{$purpose}_verified_{$subjectId}";
    }
}
