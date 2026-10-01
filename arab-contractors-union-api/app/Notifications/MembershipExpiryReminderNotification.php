<?php

namespace App\Notifications;

use App\Models\Membership;
use App\Notifications\Channels\FcmChannel;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * تذكير المقاول بقرب انتهاء عضويته (بريد + SMS + إشعار داخل التطبيق).
 */
class MembershipExpiryReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Membership $membership,
        public int $daysLeft,
    ) {
    }

    public function via(object $notifiable): array
    {
        $channels = ['database', SmsChannel::class, FcmChannel::class];

        // قناة البريد تتجاهل من لا بريد له بصمت — نتحقق صراحة
        if (! empty($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $expires = $this->membership->expires_at?->toDateString();

        return (new MailMessage)
            ->subject('تذكير بتجديد عضوية اتحاد المقاولين')
            ->greeting("الأخ الكريم / {$notifiable->name}")
            ->line("نودّ تذكيركم بأن اشتراك عضويتكم في الاتحاد ينتهي بتاريخ {$expires} (بعد {$this->daysLeft} يوم).")
            ->line('يُرجى المبادرة بتسديد رسوم التجديد لتفادي انقطاع الخدمات وشهادات العضوية.')
            ->line('للاستفسار يمكنكم التواصل مع دائرة المالية في الاتحاد.')
            ->salutation('اتحاد المقاولين الفلسطينيين — غزة');
    }

    public function toSms(object $notifiable): string
    {
        $expires = $this->membership->expires_at?->format('Y/m/d');

        return "اتحاد المقاولين: عضويتكم تنتهي بتاريخ {$expires}. يُرجى المبادرة بتجديد الاشتراك.";
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'          => 'membership_expiry_reminder',
            'title'         => 'تذكير بتجديد العضوية',
            'message'       => "عضويتك تنتهي بعد {$this->daysLeft} يوم — بادِر بتجديد الاشتراك.",
            'membership_id' => $this->membership->id,
            'expires_at'    => $this->membership->expires_at?->toDateString(),
            'days_left'     => $this->daysLeft,
        ];
    }
}
