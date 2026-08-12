<?php

namespace App\Notifications;

use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** إشعار مستهدَف يُنشئه الأدمن يدوياً من لوحة التحكم (REQ-22) — database + push معاً. */
class AdminBroadcastNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected string $title, protected string $body, protected array $data = [])
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return array_merge([
            'type'    => 'admin_broadcast',
            'title'   => $this->title,
            'message' => $this->body,
        ], $this->data);
    }
}
