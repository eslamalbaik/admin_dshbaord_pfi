<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * إشعار للمقاول عند تأكيد موظف المحاسبة لعملية الدفع — database + push.
 */
class PaymentConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Payment $payment)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'payment_confirmed',
            'payment_id' => $this->payment->id,
            'amount'     => $this->payment->amount,
            'title'      => 'تم تأكيد عملية الدفع',
            'message'    => "تم تأكيد عملية الدفع بمبلغ {$this->payment->amount} بنجاح.",
        ];
    }
}
