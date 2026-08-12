<?php

namespace App\Console\Commands;

use App\Models\Tender;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/** REQ-10: حذف العطاءات المؤرشفة منذ أكثر من 12 شهراً — تشغيل أسبوعي. */
class PurgeArchivedTenders extends Command
{
    protected $signature = 'tenders:purge {--dry-run : عرض عدد العطاءات المستهدَفة دون حذفها}';

    protected $description = 'حذف العطاءات المؤرشفة منذ أكثر من 12 شهراً';

    public function handle(): int
    {
        $query = Tender::whereNotNull('archived_at')
            ->where('archived_at', '<', now()->subMonths(12));

        if ($this->option('dry-run')) {
            $count = $query->count();
            $this->info("سيتم حذف {$count} عطاء مؤرشف منذ أكثر من 12 شهراً (dry-run — لم يُحذف شيء).");

            return self::SUCCESS;
        }

        $count = $query->count();
        $query->get()->each->forceDelete(); // حذف نهائي — لا حاجة للاحتفاظ بها بعد سنة أرشفة

        Log::channel('reminders')->info('tenders.purged', ['count' => $count]);
        $this->info("تم حذف {$count} عطاء مؤرشف.");

        return self::SUCCESS;
    }
}
