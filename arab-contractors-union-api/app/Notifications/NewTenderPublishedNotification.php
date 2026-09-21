<?php

namespace App\Notifications;

use App\Models\Tender;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * إشعار للمقاول عند نشر عطاء جديد — database + push.
 * يُرسَل من TenderController@store لكل مقاول غير محظور.
 */
class NewTenderPublishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Tender $tender)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'             => 'new_tender_published',
            'tender_id'        => $this->tender->id,
            'reference_number' => $this->tender->reference_number,
            'category'         => $this->tender->category,
            'title'            => 'عطاء جديد',
            'message'          => "تم نشر عطاء جديد: {$this->tender->title}",
        ];
    }
}
