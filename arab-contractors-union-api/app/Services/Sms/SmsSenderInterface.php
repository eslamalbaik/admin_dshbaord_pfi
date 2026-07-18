<?php

namespace App\Services\Sms;

interface SmsSenderInterface
{
    /**
     * إرسال رسالة نصية لرقم هاتف. يعيد true عند النجاح.
     */
    public function send(string $phone, string $message): bool;
}
