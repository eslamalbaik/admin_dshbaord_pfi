<?php

namespace App\Services\Push;

interface PushSenderInterface
{
    /**
     * إرسال إشعار Push لجهاز واحد عبر رمز FCM. يعيد true عند النجاح.
     */
    public function send(string $fcmToken, string $title, string $body, array $data = []): bool;
}
