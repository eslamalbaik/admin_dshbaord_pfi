<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * إشعار للإدارة عند إضافة المقاول رسالة متابعة على تذكرة قائمة.
 */
class SupportTicketContractorRepliedNotification extends Notification
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
            'type'            => 'support_ticket_contractor_replied',
            'ticket_id'       => $this->ticket->id,
            'contractor_id'   => $contractor?->id,
            'contractor_name' => $contractor?->name,
            'subject'         => $this->ticket->subject,
            'message'         => "رسالة متابعة جديدة من {$contractor?->name} على طلب: {$this->ticket->subject}",
        ];
    }
}
