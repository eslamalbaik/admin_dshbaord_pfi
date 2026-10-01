<?php

namespace App\Notifications;

use App\Models\Contractor;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SubscriptionExpiryReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Contractor $contractor,
        public ?Carbon $expiryDate = null
    ) {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        // Add FCM channel if contractor has device token
        if ($notifiable->fcm_token) {
            $channels[] = 'fcm';
        }

        return $channels;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $expiryDateFormatted = $this->expiryDate
            ? $this->expiryDate->locale('ar')->translatedFormat('Y-m-d')
            : 'قريباً';

        return [
            'type' => 'expiry_reminder',
            'title' => 'تحذير: انتهاء الاشتراك قريبا',
            'body' => "اشتراكك ينتهي في {$expiryDateFormatted}. يرجى التجديد قبل انتهاء الصلاحية.",
            'reference_type' => 'renewal',
            'action_url' => '/contractor/renewal',
        ];
    }

    /**
     * Get the FCM representation of the notification (for push delivery).
     *
     * @return array<string, mixed>
     */
    public function toFcm(object $notifiable): array
    {
        $expiryDateFormatted = $this->expiryDate
            ? $this->expiryDate->locale('ar')->translatedFormat('Y-m-d')
            : 'قريباً';

        return [
            'title' => 'تحذير: انتهاء الاشتراك قريبا',
            'body' => "اشتراكك ينتهي في {$expiryDateFormatted}. يرجى التجديد قبل انتهاء الصلاحية.",
            'data' => [
                'type' => 'expiry_reminder',
                'reference_type' => 'renewal',
                'action_url' => '/contractor/renewal',
            ],
        ];
    }
}
