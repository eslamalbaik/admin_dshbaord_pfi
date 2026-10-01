<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/** يؤرشف الفعاليات التي تجاوز موعدها اليوم — تشغيل يومي (نفس نمط tenders:archive). */
class ArchiveExpiredEvents extends Command
{
    protected $signature = 'events:archive';

    protected $description = 'أرشفة الفعاليات التي تجاوز موعدها';

    public function handle(): int
    {
        $archivedCount = Event::whereNotNull('event_date')
            ->where('event_date', '<', now())
            ->whereNull('archived_at')
            ->update(['archived_at' => now()]);

        Log::channel('reminders')->info('events.archived', ['archived' => $archivedCount]);
        $this->info("تمت أرشفة {$archivedCount} فعالية.");

        return self::SUCCESS;
    }
}
