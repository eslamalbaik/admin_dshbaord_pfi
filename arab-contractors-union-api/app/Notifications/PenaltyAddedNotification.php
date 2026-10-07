<?php

namespace App\Notifications;

use App\Models\Penalty;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * إشعار للمقاول لما تنسجّل عليه غرامة — database + push. المبلغ بالرسالة هو المتبقي بعد ما
 * ينصرف عليها رصيده السابق (لو عنده).
 */
class PenaltyAddedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Penalty $penalty)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        $amount    = number_format((float) $this->penalty->amount, 2, '.', '');
        $remaining = number_format($this->penalty->remaining, 2, '.', '');
        $reason    = $this->penalty->reason ?: 'غرامة';

        $message = match (true) {
            $this->penalty->status === 'paid' => "تم تسجيل غرامة ({$reason}) بقيمة {$amount} د.أ وتم خصمها بالكامل من رصيدك.",
            (float) $this->penalty->paid_amount > 0 => "تم تسجيل غرامة ({$reason}) بقيمة {$amount} د.أ، خُصم جزء منها من رصيدك والمتبقي {$remaining} د.أ.",
            default => "تم تسجيل غرامة ({$reason}) بقيمة {$amount} د.أ على حسابك.",
        };

        return [
            'type'       => 'penalty_added',
            'penalty_id' => $this->penalty->id,
            'amount'     => $amount,
            'remaining'  => $remaining,
            'title'      => 'غرامة جديدة على حسابك',
            'message'    => $message,
        ];
    }
}
