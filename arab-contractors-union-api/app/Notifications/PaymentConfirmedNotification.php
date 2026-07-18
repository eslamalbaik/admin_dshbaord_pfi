<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * إشعار للمقاول عند تأكيد موظف المحاسبة لعملية الدفع.
 */
class PaymentConfirmedNotification extends Notification
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
            'type'       => 'payment_confirmed',
            'payment_id' => $this->payment->id,
            'amount'     => $this->payment->amount,
            'message'    => "تم تأكيد عملية الدفع بمبلغ {$this->payment->amount} بنجاح.",
        ];
    }
}
