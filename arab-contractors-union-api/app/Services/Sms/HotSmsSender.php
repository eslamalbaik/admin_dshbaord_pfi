<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * مرسل SMS عبر HotSMS (hotsms.ps) — تفويض بـapi_token عبر GET.
 * أرقام محلية (05XXXXXXXX) تُحوَّل لصيغة دولية (972XXXXXXXXX) قبل الإرسال؛
 * hotsms يرفض الرقم بلا رمز الدولة.
 */
class HotSmsSender implements SmsSenderInterface
{
    public function __construct(
        private readonly string $apiToken,
        private readonly string $sender,
    ) {
    }

    public function send(string $phone, string $message): bool
    {
        $mobile = $this->toInternational($phone);

        $response = Http::timeout(10)->get('http://hotsms.ps/sendbulksms.php', [
            'api_token' => $this->apiToken,
            'sender'    => $this->sender,
            'mobile'    => $mobile,
            'type'      => 2, // UTF-8 متعدد اللغات — رسائل التحقق عربية
            'text'      => $message,
        ]);

        $result = trim($response->body());

        // رموز النجاح: "1001" أو "1001_<message_id>" — أي شيء آخر خطأ (راجع رموز الخطأ بتوثيق hotsms)
        if (! str_starts_with($result, '1001')) {
            Log::channel('sms')->error('HotSMS send failed', [
                'phone' => $phone, 'mobile' => $mobile, 'result' => $result,
            ]);

            return false;
        }

        Log::channel('sms')->info('HotSMS sent', ['phone' => $phone, 'mobile' => $mobile, 'result' => $result]);

        return true;
    }

    private function toInternational(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        // إزالة رمز الدولة إن وُجد قبل إعادة تركيبه بصيغة hotsms — 970 الرسمي
        // لفلسطين، و972 مستخدَم فعلياً أيضاً بسبب تشارك الشبكة مع إسرائيل
        $digits = preg_replace('/^(970|972)/', '', $digits);

        return '972' . ltrim($digits, '0');
    }
}
