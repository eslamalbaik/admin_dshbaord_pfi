<?php

namespace App\Notifications;

use App\Models\ContractorNameChangeRequest;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * إشعار للمقاول عند تغيّر حالة طلب تعديل اسم الشركة (موافقة / رفض).
 */
class NameChangeRequestStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected ContractorNameChangeRequest $request)
    {
    }

    public function via(object $notifiable): array
    {
        $channels = ['database', FcmChannel::class];
        if ($notifiable->email) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    private function statusMessage(): string
    {
        return match ($this->request->status) {
            'approved' => "تمت الموافقة على طلب تعديل اسم شركتك إلى \"{$this->request->requested_name}\".",
            'rejected' => "نأسف، تم رفض طلب تعديل اسم الشركة. السبب: {$this->request->reject_reason}",
            default    => 'تم تحديث حالة طلب تعديل اسم الشركة.',
        };
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'name_change_request_status',
            'request_id' => $this->request->id,
            'status'     => $this->request->status,
            'title'      => $this->request->status === 'approved'
                ? 'تمت الموافقة على تعديل اسم الشركة'
                : 'تم رفض طلب تعديل اسم الشركة',
            'message'    => $this->statusMessage(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('تحديث على طلب تعديل اسم الشركة — اتحاد المقاولين الفلسطينيين')
            ->greeting("مرحبًا {$notifiable->name}")
            ->line($this->statusMessage())
            ->salutation('مع تحيات اتحاد المقاولين الفلسطينيين');
    }
}
