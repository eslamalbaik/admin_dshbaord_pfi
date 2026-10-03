<?php

namespace App\Jobs;

use App\Models\Contractor;
use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * إشعار "فعالية جديدة" للمقاولين وقت ما تبين الفعالية فعلياً، مش وقت الحفظ: الفعالية المجدولة
 * لتاريخ لاحق كانت تبعت الإشعار فوراً وهي لسا مخفية عن التطبيق. يُرسَل مؤجلاً لتاريخ النشر،
 * وعند التشغيل يتأكد إن الفعالية لسا منشورة وبنفس التاريخ — لو انحذفت أو صارت مسودة أو
 * انعادت جدولتها (اللي بتبعت job جديد بتاريخها الجديد) بيطلع بدون ما يبعت شي.
 */
class SendEventPublishedPushJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private int $eventId,
        private string $expectedPublishedAt,
    ) {
    }

    /** يبعت الإشعار فوراً لو تاريخ النشر وصل، أو مؤجلاً لتاريخ النشر لو لسا لقدّام */
    public static function dispatchFor(Event $event): void
    {
        if (! $event->is_published || ! $event->published_at)
            return;

        $pending = static::dispatch($event->id, $event->published_at->toIso8601String());

        if ($event->published_at->isFuture())
            $pending->delay($event->published_at);
    }

    public function handle(): void
    {
        $event = Event::find($this->eventId);

        if (! $event || ! $event->is_published || ! $event->published_at)
            return;

        if (! $event->published_at->equalTo(Carbon::parse($this->expectedPublishedAt)))
            return;

        SendPushToContractorsJob::dispatch(
            Contractor::whereNotNull('fcm_token')->pluck('id')->all(),
            'فعالية جديدة',
            $event->title,
            ['type' => 'event', 'news_id' => (string) $event->id],
        );
    }
}
