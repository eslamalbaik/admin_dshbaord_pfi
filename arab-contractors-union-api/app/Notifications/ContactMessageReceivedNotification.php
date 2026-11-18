<?php

namespace App\Notifications;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * إشعار للإدارة عند وصول رسالة جديدة من نموذج "تواصل معنا" بالموقع.
 */
class ContactMessageReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected ContactMessage $contactMessage)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'contact_message_received',
            'message_id' => $this->contactMessage->id,
            'sender'     => $this->contactMessage->name,
            'message'    => "رسالة جديدة من {$this->contactMessage->name} عبر الموقع: {$this->contactMessage->subject}",
        ];
    }
}
