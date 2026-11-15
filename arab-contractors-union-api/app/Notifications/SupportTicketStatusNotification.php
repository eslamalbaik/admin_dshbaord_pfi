<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * إشعار للمقاول عن تذكرة الدعم الفني: "تم الاستلام" فور فتحها (عشان ما يظن إن طلبه انهمل
 * لو الرد تأخر)، وعند تحويلها لـ"قيد المعالجة" أو إغلاقها. رد الإدارة نفسه بدون إشعار (قرار الإدارة).
 */
class SupportTicketStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param string $event received | in_progress | closed */
    public function __construct(protected SupportTicket $ticket, protected string $event)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        $subject = $this->ticket->subject;

        [$title, $message] = match ($this->event) {
            'in_progress' => ['تذكرتك قيد المعالجة', "طلبك «{$subject}» قيد المعالجة حالياً من فريق الدعم الفني، وسنوافيك بالرد فور جاهزيته."],
            'closed'      => ['تم إغلاق تذكرتك', "تم إغلاق طلبك «{$subject}»."],
            default       => ['تم استلام تذكرتك', "استلمنا طلبك «{$subject}» وسيتم الرد عليك بأقرب وقت."],
        };

        return [
            'type'      => 'support_ticket_status',
            'ticket_id' => $this->ticket->id,
            'status'    => $this->ticket->status,
            'title'     => $title,
            'message'   => $message,
        ];
    }
}
