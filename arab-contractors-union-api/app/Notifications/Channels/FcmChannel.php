<?php

namespace App\Notifications\Channels;

use App\Services\Push\PushSenderInterface;
use Illuminate\Notifications\Notification;

/**
 * قناة إشعارات Push (FCM) — يستخدمها أي Notification يعرّف toFcm()،
 * أو تلقائياً من toArray() (title/message) إن لم يُعرَّف toFcm() صراحة.
 * راجع App\Notifications\Channels\SmsChannel لنفس النمط.
 */
class FcmChannel
{
    public function __construct(private PushSenderInterface $sender)
    {
    }

    public function send(object $notifiable, Notification $notification): void
    {
        $token = $notifiable->routeNotificationFor('fcm', $notification)
            ?? $notifiable->fcm_token
            ?? null;

        if (! $token) {
            return;
        }

        if (method_exists($notification, 'toFcm')) {
            $payload = $notification->toFcm($notifiable);
        } elseif (method_exists($notification, 'toArray')) {
            $array = $notification->toArray($notifiable);
            $payload = [
                'title' => $array['title'] ?? 'اتحاد المقاولين الفلسطينيين',
                'body'  => $array['message'] ?? '',
                'data'  => $array,
            ];
        } else {
            return;
        }

        if (empty($payload['body'])) {
            return;
        }

        $this->sender->send($token, $payload['title'], $payload['body'], $payload['data'] ?? []);
    }
}
