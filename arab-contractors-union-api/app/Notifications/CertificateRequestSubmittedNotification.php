<?php

namespace App\Notifications;

use App\Models\CertificateRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * إشعار للإدارة عند تقديم مقاول طلب شهادة عضوية جديد.
 */
class CertificateRequestSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected CertificateRequest $certificateRequest)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'certificate_request_submitted',
            'request_id' => $this->certificateRequest->id,
            'contractor' => $this->certificateRequest->contractor?->name,
            'cert_type'  => $this->certificateRequest->type,
            'message'    => "طلب شهادة جديد ({$this->certificateRequest->type_label}) من المقاول {$this->certificateRequest->contractor?->name}",
        ];
    }
}
