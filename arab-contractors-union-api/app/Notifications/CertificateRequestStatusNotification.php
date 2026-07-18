<?php

namespace App\Notifications;

use App\Models\CertificateRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * إشعار للمقاول عند تغيّر حالة طلب الشهادة (موافقة / إصدار / رفض)
 * — عبر البريد الإلكتروني وإشعار داخل التطبيق.
 */
class CertificateRequestStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected CertificateRequest $certificateRequest)
    {
    }

    public function via(object $notifiable): array
    {
        return $notifiable->email ? ['database', 'mail'] : ['database'];
    }

    private function statusMessage(): string
    {
        return match ($this->certificateRequest->status) {
            'approved' => "تمت الموافقة على طلبك ({$this->certificateRequest->type_label}) وجارٍ إصدار الشهادة.",
            'issued'   => "تم إصدار شهادتك ({$this->certificateRequest->type_label}) ويمكنك تحميلها من التطبيق.",
            'rejected' => "نأسف، تم رفض طلبك ({$this->certificateRequest->type_label}). السبب: {$this->certificateRequest->reject_reason}",
            default    => "تم تحديث حالة طلبك ({$this->certificateRequest->type_label}).",
        };
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'certificate_request_status',
            'request_id' => $this->certificateRequest->id,
            'status'     => $this->certificateRequest->status,
            'message'    => $this->statusMessage(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('تحديث على طلب الشهادة — اتحاد المقاولين الفلسطينيين')
            ->greeting("مرحبًا {$notifiable->name}")
            ->line($this->statusMessage());

        if ($this->certificateRequest->status === 'issued' && $this->certificateRequest->certificate_url) {
            $mail->action('تحميل الشهادة', $this->certificateRequest->certificate_url);
        }

        return $mail->salutation('مع تحيات اتحاد المقاولين الفلسطينيين');
    }
}
