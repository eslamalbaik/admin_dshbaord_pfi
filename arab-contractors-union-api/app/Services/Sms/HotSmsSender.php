<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * مرسل SMS عبر HotSMS REST API v2 (hotsms.ps) — تفويض Bearer token
 * (وليس api_token بالرابط كما بالإصدار القديم v1).
 * أرقام محلية (05XXXXXXXX) تُحوَّل لصيغة دولية (+970XXXXXXXXX) قبل الإرسال.
 */
class HotSmsSender implements SmsSenderInterface
{
    private const BASE_URL = 'https://hotsms.ps/api/rest/v2';

    public function __construct(
        private readonly string $apiToken,
        private readonly string $sender,
    ) {
    }

    public function send(string $phone, string $message): bool
    {
        $mobile = $this->toInternational($phone);

        $response = Http::withToken($this->apiToken)
            ->timeout(10)
            ->post(self::BASE_URL . '/messages/send', [
                'mobile'  => $mobile,
                'message' => $message,
                'sender'  => $this->sender,
            ]);

        $body = $response->json() ?? [];

        if (! ($body['status'] ?? false)) {
            Log::channel('sms')->error('HotSMS send failed', [
                'phone' => $phone, 'mobile' => $mobile, 'http_status' => $response->status(), 'response' => $body,
            ]);

            return false;
        }

        Log::channel('sms')->info('HotSMS sent', ['phone' => $phone, 'mobile' => $mobile, 'response' => $body]);

        return true;
    }

    private function toInternational(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        // إزالة رمز الدولة إن وُجد قبل إعادة تركيبه — 970 رمز فلسطين المعتمَد
        // بتوثيق hotsms v2، و972 يظهر أحياناً بسبب تشارك الشبكة مع إسرائيل
        $digits = preg_replace('/^(970|972)/', '', $digits);

        return '+970' . ltrim($digits, '0');
    }
}
