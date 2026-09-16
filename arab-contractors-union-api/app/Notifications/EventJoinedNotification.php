<?php

namespace App\Notifications;

use App\Models\Contractor;
use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * إشعار للإدارة عند تسجيل مقاول حضوره لفعالية (RSVP) لأول مرة.
 */
class EventJoinedNotification extends Notification
{
    use Queueable;

    public function __construct(protected Event $event, protected Contractor $contractor)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'            => 'event_joined',
            'event_id'        => $this->event->id,
            'event_title'     => $this->event->title,
            'contractor_id'   => $this->contractor->id,
            'contractor_name' => $this->contractor->name,
            'message'         => "سجّل {$this->contractor->name} حضوره لفعالية \"{$this->event->title}\".",
        ];
    }
}
