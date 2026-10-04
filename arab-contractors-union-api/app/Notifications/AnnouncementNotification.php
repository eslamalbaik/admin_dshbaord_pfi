<?php

namespace App\Notifications;

use App\Models\Announcement;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AnnouncementNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Announcement $announcement)
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
            'type' => 'announcement',
            'title' => 'إعلان جديد',
            'body' => $this->announcement->title,
            'reference_id' => $this->announcement->id,
            'reference_type' => 'announcement',
            'action_url' => "/contractor/announcements/{$this->announcement->id}",
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
            'title' => 'إعلان جديد',
            'body' => $this->announcement->title,
            'data' => [
                'type' => 'announcement',
                'reference_id' => $this->announcement->id,
                'reference_type' => 'announcement',
                'action_url' => "/contractor/announcements/{$this->announcement->id}",
            ],
        ];
    }
}
