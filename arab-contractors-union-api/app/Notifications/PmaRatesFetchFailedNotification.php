<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** تنبيه للمطوّر/الإدارة عند فشل جلب أسعار سلطة النقد اليومية (REQ-17 fallback). */
class PmaRatesFetchFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected string $reason)
    {
    }

    public function via(object $notifiable): array
    {
        return $notifiable->email ? ['database', 'mail'] : ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'    => 'pma_rates_fetch_failed',
            'title'   => 'فشل جلب أسعار الصرف من سلطة النقد',
            'message' => "تعذّر جلب سعر الصرف اليومي — تم الاعتماد على آخر سعر محفوظ. السبب: {$this->reason}",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('فشل جلب أسعار الصرف اليومية — سلطة النقد الفلسطينية')
            ->greeting("مرحبًا {$notifiable->name}")
            ->line('فشل أمر `rates:fetch-pma` اليوم بجلب أسعار الصرف الرسمية من موقع سلطة النقد.')
            ->line("السبب: {$this->reason}")
            ->line('تم الاعتماد تلقائياً على آخر سعر صرف محفوظ بالنظام (fallback) لتفادي توقف عمليات الدفع.')
            ->line('يُرجى مراجعة سعر الصرف يدوياً من لوحة التحكم إن استمر العطل.')
            ->salutation('نظام اتحاد المقاولين الفلسطينيين');
    }
}
