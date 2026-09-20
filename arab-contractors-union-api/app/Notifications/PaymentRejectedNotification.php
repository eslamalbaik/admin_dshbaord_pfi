<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * إشعار للمقاول عند رفض موظف المحاسبة لإشعار التحويل — database + push.
 */
class PaymentRejectedNotification extends Notification implements ShouldQueue
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
            'type'             => 'payment_rejected',
            'payment_id'       => $this->payment->id,
            'amount'           => $this->payment->amount,
            'rejection_reason' => $this->payment->rejection_reason,
            'title'            => 'تم رفض إشعار التحويل',
            'message'          => "تم رفض إشعار التحويل بمبلغ {$this->payment->amount}. السبب: {$this->payment->rejection_reason}",
        ];
    }
}
