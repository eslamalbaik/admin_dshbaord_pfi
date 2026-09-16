<?php

namespace App\Notifications;

use App\Models\Contractor;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * إشعار للإدارة عند تفعيل مقاول لحسابه لأول مرة (إتمام تسجيل كلمة المرور).
 */
class ContractorActivatedNotification extends Notification
{
    use Queueable;

    public function __construct(protected Contractor $contractor)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'              => 'contractor_activated',
            'contractor_id'     => $this->contractor->id,
            'contractor_name'   => $this->contractor->name,
            'membership_number' => $this->contractor->membership_number,
            'message'           => "قام {$this->contractor->name} بتفعيل حسابه لأول مرة.",
        ];
    }
}
