<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * إشعار للإدارة عند إرسال المقاول لتذكرة دعم/شكوى جديدة.
 */
class SupportTicketCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(protected SupportTicket $ticket)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $contractor = $this->ticket->contractor;

        return [
            'type'            => 'support_ticket_created',
            'ticket_id'       => $this->ticket->id,
            'contractor_id'   => $contractor?->id,
            'contractor_name' => $contractor?->name,
            'subject'         => $this->ticket->subject,
            'category'        => $this->ticket->category,
            'message'         => "طلب دعم جديد ({$this->ticket->category_label}) من {$contractor?->name}: {$this->ticket->subject}",
        ];
    }
}
