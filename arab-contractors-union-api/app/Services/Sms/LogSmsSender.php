<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * مرسل SMS تجريبي — يكتب الرسالة في السجل بدل إرسالها.
 * يُستبدل بمزود فعلي (Jawwal/TweetSMS...) عبر SMS_DRIVER في .env عند التعاقد.
 */
class LogSmsSender implements SmsSenderInterface
{
    public function send(string $phone, string $message): bool
    {
        Log::channel('sms')->info('SMS (log driver)', ['phone' => $phone, 'message' => $message]);

        return true;
    }
}
