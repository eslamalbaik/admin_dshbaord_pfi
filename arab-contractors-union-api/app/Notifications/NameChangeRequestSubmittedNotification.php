<?php

namespace App\Notifications;

use App\Models\ContractorNameChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * إشعار للإدارة عند تقديم مقاول طلب تعديل اسم الشركة.
 */
class NameChangeRequestSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected ContractorNameChangeRequest $request)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'            => 'name_change_request_submitted',
            'request_id'      => $this->request->id,
            'contractor'      => $this->request->contractor?->name,
            'requested_name'  => $this->request->requested_name,
            'message'         => "طلب تعديل اسم شركة من المقاول {$this->request->current_name} إلى \"{$this->request->requested_name}\"",
        ];
    }
}
