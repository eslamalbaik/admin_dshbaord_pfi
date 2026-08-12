<?php

namespace App\Notifications;

use App\Models\ProfileUpdateRequest;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** إشعار المقاول بنتيجة طلب تعديل بياناته (REQ-26) — database + push، بريد إن توفّر. */
class ProfileUpdateRequestStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected ProfileUpdateRequest $request)
    {
    }

    public function via(object $notifiable): array
    {
        $channels = ['database', FcmChannel::class];
        if (! empty($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        $approved = $this->request->status === 'approved';

        return [
            'type'       => 'profile_update_request_status',
            'request_id' => $this->request->id,
            'title'      => $approved ? 'تمت الموافقة على طلب تعديل البيانات' : 'تم رفض طلب تعديل البيانات',
            'message'    => $approved
                ? 'تم تحديث بياناتك بنجاح.'
                : 'تم رفض طلبك' . ($this->request->reject_reason ? ": {$this->request->reject_reason}" : '.'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $approved = $this->request->status === 'approved';
        $mail = (new MailMessage)
            ->subject($approved ? 'تمت الموافقة على طلب تعديل بياناتك' : 'تم رفض طلب تعديل بياناتك')
            ->greeting("مرحبًا {$notifiable->name}");

        if ($approved) {
            $mail->line('تمت الموافقة على طلب تعديل بياناتك، وتم تحديثها بنجاح.');
        } else {
            $mail->line('نأسف، تم رفض طلب تعديل بياناتك.');
            if ($this->request->reject_reason) {
                $mail->line("السبب: {$this->request->reject_reason}");
            }
        }

        return $mail->salutation('اتحاد المقاولين الفلسطينيين');
    }
}
