<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** تذكير يومي للمراجعين: طلبات تعديل ملفات تعدّت مهلة المراجعة. */
class ProfileChangesOverdueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private int $count, private int $slaDays)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'    => 'contractor_profile_changes_overdue',
            'count'   => $this->count,
            'title'   => 'طلبات تعديل ملفات متأخرة',
            'message' => "{$this->count} طلب تعديل ملف مقاول ينتظر المراجعة منذ أكثر من {$this->slaDays} يوم عمل.",
        ];
    }
}
