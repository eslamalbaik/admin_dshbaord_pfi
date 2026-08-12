<?php

namespace App\Console\Commands;

use App\Models\Tender;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/** REQ-09: يؤرشف العطاءات التي تجاوزت موعد التسليم — تشغيل يومي. */
class ArchiveExpiredTenders extends Command
{
    protected $signature = 'tenders:archive';

    protected $description = 'أرشفة العطاءات التي تجاوز موعد تسليمها اليوم';

    public function handle(): int
    {
        $count = Tender::whereNull('archived_at')
            ->whereNotNull('deadline')
            ->where('deadline', '<', now()->toDateString())
            ->update(['archived_at' => now()]);

        Log::channel('reminders')->info('tenders.archived', ['count' => $count]);
        $this->info("تمت أرشفة {$count} عطاء.");

        return self::SUCCESS;
    }
}
