<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * إشعار للمقاول عند رد الإدارة على تذكرته — عبر البريد الإلكتروني
 * وإشعار داخل التطبيق (كما هو مطلوب في شاشة الدعم الفني).
 */
class SupportTicketRepliedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected SupportTicket $ticket)
    {
    }

    public function via(object $notifiable): array
    {
        // إشعار داخل التطبيق دائمًا، والبريد إن توفّر بريد للمقاول.
        return $notifiable->email ? ['database', 'mail'] : ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'      => 'support_ticket_replied',
            'ticket_id' => $this->ticket->id,
            'subject'   => $this->ticket->subject,
            'reply'     => $this->ticket->reply,
            'message'   => "تم الرد على طلبك: {$this->ticket->subject}",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('رد على طلب الدعم — اتحاد المقاولين العرب')
            ->greeting("مرحبًا {$notifiable->name}")
            ->line("لقد تم الرد على طلبك: «{$this->ticket->subject}»")
            ->line('نص الرد:')
            ->line($this->ticket->reply)
            ->line('يمكنك متابعة طلبك من خلال التطبيق.')
            ->salutation('مع تحيات اتحاد المقاولين العرب');
    }
}
