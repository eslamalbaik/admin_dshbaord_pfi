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

        // مزامنة display_status مع status بعد الإغلاق التلقائي أعلاه (وأي إغلاق/إلغاء يدوي لم يُطابَق بعد)
        $displayClosedCount = Tender::whereIn('status', ['closed', 'cancelled'])
            ->where('display_status', '!=', 'closed')
            ->update(['display_status' => 'closed']);

        // دخول نافذة "ينتهي قريباً" — تحوّل زمني بحت، دون أي تغيير على status الإداري
        $displayClosingSoonCount = Tender::whereNotIn('status', ['closed', 'cancelled'])
            ->whereNotNull('deadline')
            ->whereBetween('deadline', [now()->toDateString(), now()->addDays(Tender::CLOSING_SOON_DAYS)->toDateString()])
            ->whereNotIn('display_status', ['closing_soon', 'closed'])
            ->update(['display_status' => 'closing_soon']);

        Log::channel('reminders')->info('tenders.archived', [
            'archived' => $archivedCount,
            'closed' => $closedCount,
            'display_closed' => $displayClosedCount,
            'display_closing_soon' => $displayClosingSoonCount,
        ]);
        $this->info("تمت أرشفة {$archivedCount} عطاء، وإغلاق {$closedCount} منها تلقائياً.");

        return self::SUCCESS;
    }
}
