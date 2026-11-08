<?php

namespace App\Services\Sms;

use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * مرسل SMS عبر HotSMS (hotsms.ps) — يدعم واجهتين حسب HOTSMS_API:
 *  - v1 (الافتراضي): GET sendbulksms.php?api_token=..&sender=..&mobile=970..&type=..&text=..
 *    وهي الواجهة المفعّلة على حساب الاتحاد.
 *  - v2: REST مع Bearer token.
 * الرقم المخزّن (0599123456 أو 962791234567 لباقي الدول) يتحوّل لصيغة دولية قبل الإرسال.
 */
class HotSmsSender implements SmsSenderInterface
{
    private const V1_URL = 'https://hotsms.ps/sendbulksms.php';

    private const V2_URL = 'https://hotsms.ps/api/rest/v2/messages/send';

    /** رموز أخطاء v1 من توثيق HotSMS — تُكتب بسجل sms لتشخيص سبب الرفض. */
    private const V1_ERRORS = [
        '1000'  => 'لا يوجد رصيد كافٍ',
        '2000'  => 'خطأ في التفويض (api_token غير صحيح)',
        '3000'  => 'خطأ في نوع الرسالة (type)',
        '4000'  => 'أحد المدخلات المطلوبة غير موجود',
        '5000'  => 'رقم المحمول غير مدعوم',
        '6000'  => 'اسم المرسل غير معرّف على الحساب',
        '10000' => 'IP السيرفر غير مفوّض للإرسال من هذا الحساب',
        '15000' => 'الإرسال عبر API غير مفعّل من أدوات الأمان بالحساب',
    ];

    public function __construct(
        private readonly string $apiToken,
        private readonly string $sender,
        private readonly string $apiVersion = 'v1',
        private readonly string $messageType = '2',
    ) {
    }

    public function send(string $phone, string $message): bool
    {
        $mobile = $this->toInternational($phone);

        try {
            $response = $this->apiVersion === 'v2'
                ? Http::withToken($this->apiToken)->timeout(10)->post(self::V2_URL, [
                    'mobile'  => '+' . $mobile,
                    'message' => $message,
                    'sender'  => $this->sender,
                ])
                : Http::timeout(10)->get(self::V1_URL, [
                    'api_token' => $this->apiToken,
                    'sender'    => $this->sender,
                    'mobile'    => $mobile,
                    'type'      => $this->messageType,
                    'text'      => $message,
                ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // انقطاع/مهلة — يُعامَل كفشل إرسال بدل خطأ 500
            Log::channel('sms')->error('HotSMS unreachable', ['phone' => $phone, 'mobile' => $mobile, 'error' => $e->getMessage()]);

            return false;
        }

        // v1 يرجّع نصاً: 1001 (أو 1001_<message id>) = أُرسلت، وأي رمز آخر خطأ (راجع V1_ERRORS).
        // v2 يُرجع JSON فيه status.
        $body = trim($response->body());
        $sent = $this->apiVersion === 'v2'
            ? (bool) ($response->json('status') ?? false)
            : $response->successful() && preg_match('/^1001(_|$)/', $body) === 1;

        $context = [
            'api' => $this->apiVersion, 'phone' => $phone, 'mobile' => $mobile,
            'http_status' => $response->status(), 'response' => mb_substr($body, 0, 500),
        ];

        if (! $sent && $this->apiVersion !== 'v2') {
            $context['reason'] = self::V1_ERRORS[$body] ?? 'رد غير معروف من HotSMS';
        }

        if (! $sent) {
            Log::channel('sms')->error('HotSMS send failed', $context);

            return false;
        }

        Log::channel('sms')->info('HotSMS sent', $context);

        return true;
    }

    /** 970599123456 / 962791234567 — أرقام فقط بدون + (صيغة v1؛ v2 تضيف + عند الإرسال) */
    private function toInternational(string $phone): string
    {
        $international = PhoneNumber::international($phone);

        if ($international && str_starts_with($international, '+')) {
            return substr($international, 1);
        }

        $digits = preg_replace('/\D/', '', $phone);

        // مكتوب دولياً صراحةً (+…) لدولة مش بجدول المقدمات — يُرسَل كما هو
        if (str_starts_with(trim($phone), '+')) {
            return $digits;
        }

        // رقم ما تعرّف عليه PhoneNumber — يُعامَل كرقم فلسطيني كما كان سابقاً
        $digits = preg_replace('/^(970|972)/', '', $digits);

        return '970' . ltrim($digits, '0');
    }
}
