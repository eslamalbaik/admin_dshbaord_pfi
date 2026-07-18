<?php

namespace App\Notifications\Channels;

use App\Services\Sms\SmsSenderInterface;
use Illuminate\Notifications\Notification;

/**
 * قناة إشعارات SMS مخصصة — يستخدمها أي Notification يعرّف toSms().
 */
class SmsChannel
{
    public function __construct(private SmsSenderInterface $sender)
    {
    }

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms')) {
            return;
        }

        $phone = $notifiable->routeNotificationFor('sms', $notification)
            ?? $notifiable->phone
            ?? null;

        if (! $phone) {
            return;
        }

        $this->sender->send($phone, $notification->toSms($notifiable));
    }
}
