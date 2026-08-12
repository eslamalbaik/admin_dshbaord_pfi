<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Log;

/**
 * مرسل Push تجريبي — يكتب الإشعار في السجل بدل إرساله فعلياً.
 * يُستخدم تلقائياً حين لا يوجد ملف اعتماد Firebase مُعرَّف (FIREBASE_CREDENTIALS في .env).
 */
class LogPushSender implements PushSenderInterface
{
    public function send(string $fcmToken, string $title, string $body, array $data = []): bool
    {
        Log::channel('push')->info('Push (log driver)', [
            'fcm_token' => $fcmToken,
            'title'     => $title,
            'body'      => $body,
            'data'      => $data,
        ]);

        return true;
    }
}
