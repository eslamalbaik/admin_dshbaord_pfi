<?php

namespace App\Notifications;

use App\Models\ProfileUpdateRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** إشعار الإدارة بوجود طلب تعديل بروفايل جديد بانتظار المراجعة (REQ-26). */
class ProfileUpdateRequestSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected ProfileUpdateRequest $request)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'profile_update_request_submitted',
            'request_id' => $this->request->id,
            'message'    => "طلب تعديل بيانات جديد من {$this->request->contractor?->name}",
        ];
    }
}
