<?php

namespace App\Notifications;

use App\Models\Contractor;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SubscriptionPaymentReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Contractor $contractor)
    {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // FcmChannel::class لا 'fcm' — ما في قناة مسجّلة بهالاسم، فكانت مهمة الـ push بالطابور
        // تفشل بـ "Driver [fcm] not supported". القناة نفسها بتتخطّى المقاول بلا fcm_token.
        return ['database', FcmChannel::class];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_reminder',
            'title' => 'تذكير بدفع الاشتراك',
            'body' => 'لديك اشتراك مستحق. يرجى الدفع الآن.',
            'reference_type' => 'payment',
            'action_url' => '/contractor/payment',
        ];
    }

    /**
     * Get the FCM representation of the notification (for push delivery).
     *
     * @return array<string, mixed>
     */
    public function toFcm(object $notifiable): array
    {
        return [
            'title' => 'تذكير بدفع الاشتراك',
            'body' => 'لديك اشتراك مستحق. يرجى الدفع الآن.',
            'data' => [
                'type' => 'payment_reminder',
                'reference_type' => 'payment',
                'action_url' => '/contractor/payment',
            ],
        ];
    }
}
