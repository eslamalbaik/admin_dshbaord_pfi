<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * إشعار لموظفي المحاسبة عند رفع مقاول لإشعار تحويل جديد بانتظار التأكيد.
 */
class PaymentSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(protected Payment $payment)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $contractor = $this->payment->contractor;

        return [
            'type'            => 'payment_submitted',
            'payment_id'      => $this->payment->id,
            'contractor_id'   => $contractor?->id,
            'contractor_name' => $contractor?->name,
            'amount'          => $this->payment->amount,
            'message'         => "قام {$contractor?->name} برفع إشعار تحويل بمبلغ {$this->payment->amount} بانتظار التأكيد.",
        ];
    }
}
