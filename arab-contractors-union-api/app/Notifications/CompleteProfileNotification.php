<?php

namespace App\Notifications;

use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * تذكير للمقاول بإكمال بياناته الشخصية — يُرسَل مرة واحدة عند الدخول
 * طالما لا يوجد إشعار سابق من نفس النوع لم تتم قراءته بعد.
 */
class CompleteProfileNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'    => 'complete_profile',
            'title'   => 'أكمل بيانات ملفك الشخصي',
            'message' => 'يجب إكمال بيانات ملفك الشخصي ومرفقاته قبل التمكّن من طلب شهادة عضوية.',
        ];
    }
}
