<?php

namespace App\Console\Commands;

use App\Models\Tender;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/** REQ-09: يؤرشف العطاءات التي تجاوزت موعد التسليم ويغلقها تلقائياً — تشغيل يومي. */
class ArchiveExpiredTenders extends Command
{
    protected $signature = 'tenders:archive';

    protected $description = 'أرشفة وإغلاق العطاءات التي تجاوز موعد تسليمها اليوم';

    public function handle(): int
    {
        $expiredBase = fn () => Tender::whereNotNull('deadline')->where('deadline', '<', now()->toDateString());

        // الإغلاق التلقائي: عطاء "مفتوح" تجاوز الموعد النهائي يتحول لـ"مغلق" دون تدخل يدوي —
        // بغض النظر عن حالة الأرشفة (عطاء مؤرشف مسبقاً بحالة "مفتوح" لازم ينغلق كمان).
        // لا نلمس "ملغى" — تلك حالة يدوية مقصودة من الأدمن.
        $closedCount = $expiredBase()->where('status', 'open')->update(['status' => 'closed']);

        $archivedCount = $expiredBase()->whereNull('archived_at')->update(['archived_at' => now()]);

        Log::channel('reminders')->info('tenders.archived', ['archived' => $archivedCount, 'closed' => $closedCount]);
        $this->info("تمت أرشفة {$archivedCount} عطاء، وإغلاق {$closedCount} منها تلقائياً.");

        return self::SUCCESS;
    }
}
