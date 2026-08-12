<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;

/**
 * مرسل Push فعلي عبر Firebase Cloud Messaging.
 * يُفعَّل فقط حين يوجد FIREBASE_CREDENTIALS صالح — راجع AppServiceProvider::register().
 */
class FirebasePushSender implements PushSenderInterface
{
    public function __construct(private Messaging $messaging)
    {
    }

    public function send(string $fcmToken, string $title, string $body, array $data = []): bool
    {
        try {
            $message = CloudMessage::withTarget('token', $fcmToken)
                ->withNotification(FirebaseNotification::create($title, $body))
                ->withData(array_map('strval', $data));

            $this->messaging->send($message);

            return true;
        } catch (\Throwable $e) {
            Log::channel('push')->error('FCM send failed', [
                'fcm_token' => $fcmToken,
                'error'     => $e->getMessage(),
            ]);

            return false;
        }
    }
}
