<?php

namespace App\Notifications;

use App\Models\Membership;
use App\Notifications\Channels\SmsChannel;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * تذكير المقاول خلال فترة السماح بعد انتهاء عضويته (بريد + SMS + إشعار داخل التطبيق).
 * الوصول للتطبيق/الموقع غير محجوب خلال هذه الفترة إلا إذا جُمّد الحساب من لوحة التحكم —
 * هذا التذكير توعوي فقط لحثّ المقاول على التجديد قبل انتهاء فترة السماح.
 */
class MembershipGracePeriodReminderNotification extends Notification
{
    use Queueable;

    /** @param 'start'|'middle'|'end' $stage */
    public function __construct(
        public Membership $membership,
        public string $stage,
        public Carbon $graceEndsAt,
    ) {
    }

    public function via(object $notifiable): array
    {
        $channels = ['database', SmsChannel::class];

        if (! empty($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    private function stageText(): string
    {
        $graceEnd = $this->graceEndsAt->translatedFormat('d/m/Y');

        return match ($this->stage) {
            'start'  => "انتهت عضويتكم بتاريخ {$this->membership->expires_at?->format('Y/m/d')}. أمامكم فترة سماح للتجديد حتى {$graceEnd}.",
            'middle' => "تذكير: عضويتكم منتهية وما زالت فترة السماح سارية حتى {$graceEnd}. يُرجى المبادرة بالتجديد.",
            'end'    => "تنبيه أخير: فترة السماح لتجديد عضويتكم تنتهي بتاريخ {$graceEnd}. بادِروا بالتجديد فوراً لتفادي فقدان مزايا العضوية.",
            default  => "يُرجى المبادرة بتجديد عضويتكم قبل انتهاء فترة السماح بتاريخ {$graceEnd}.",
        };
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('فترة السماح لتجديد عضويتكم — اتحاد المقاولين')
            ->greeting("الأخ الكريم / {$notifiable->name}")
            ->line($this->stageText())
            ->line('يُرجى المبادرة بتسديد رسوم التجديد لتفادي انقطاع الخدمات وشهادات العضوية.')
            ->line('للاستفسار يمكنكم التواصل مع دائرة المالية في الاتحاد.')
            ->salutation('اتحاد المقاولين الفلسطينيين — غزة');
    }

    public function toSms(object $notifiable): string
    {
        return "اتحاد المقاولين: {$this->stageText()}";
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'          => 'membership_grace_period_reminder',
            'title'         => 'فترة سماح تجديد العضوية',
            'message'       => $this->stageText(),
            'membership_id' => $this->membership->id,
            'expires_at'    => $this->membership->expires_at?->toDateString(),
            'grace_ends_at' => $this->graceEndsAt->toDateString(),
            'stage'         => $this->stage,
        ];
    }
}
