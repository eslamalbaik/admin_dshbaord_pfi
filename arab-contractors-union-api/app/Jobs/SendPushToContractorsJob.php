<?php

namespace App\Jobs;

use App\Models\Contractor;
use App\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * بث Push لعدة مقاولين بالخلفية — يفصل حلقة الإرسال (كل عنصر = طلب HTTP فعلي لـFCM)
 * عن دورة طلب/رد الأدمن عند نشر تعميم/فعالية. قبل هذا الفصل كانت الحلقة تعمل متزامنة
 * جوا نفس الطلب (نفس فئة باغ الـSMTP المتزامن اللي علّق تأكيد الشهادات 60 ثانية).
 */
class SendPushToContractorsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param array<int> $contractorIds
     */
    public function __construct(
        private array $contractorIds,
        private string $title,
        private string $body,
        private array $data = [],
    ) {
    }

    public function handle(PushNotificationService $push): void
    {
        $contractors = Contractor::whereIn('id', $this->contractorIds)
            ->whereNotNull('fcm_token')
            ->get();

        $push->sendToMany($contractors, $this->title, $this->body, $this->data);
    }
}
