<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * إشعار للمقاول عند رفض موظف المحاسبة لإشعار التحويل.
 */
class PaymentRejectedNotification extends Notification
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
        return [
            'type'             => 'payment_rejected',
            'payment_id'       => $this->payment->id,
            'amount'           => $this->payment->amount,
            'rejection_reason' => $this->payment->rejection_reason,
            'message'          => "تم رفض إشعار التحويل بمبلغ {$this->payment->amount}. السبب: {$this->payment->rejection_reason}",
        ];
    }
}
