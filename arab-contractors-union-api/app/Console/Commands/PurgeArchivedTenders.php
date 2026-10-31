<?php

namespace App\Console\Commands;

use App\Models\Tender;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * REQ-10 (معدَّل 2026-10-31 بطلب الإدارة): حذف العطاءات المؤرشفة منذ أكثر من 70 يوماً — تشغيل يومي.
 *
 * الحذف نهائي (forceDelete) ويشمل ملفات المرفقات وملف التقديم من التخزين. صفوف المرفقات
 * والمحفوظات تُحذف تلقائياً عبر cascadeOnDelete.
 *
 * الحماية: لا يُحذف إلا عطاء له archived_at أقدم من 70 يوماً *و* موعده النهائي فائت (أو فارغ) —
 * عطاء مُدِّد موعده بعد الأرشفة (archived_at لا يُمسح عند التعديل) يبقى بأمان.
 * كل عطاء محذوف يُسجَّل باسمه ورقمه في سجل reminders.
 */
class PurgeArchivedTenders extends Command
{
    public const RETENTION_DAYS = 70;

    protected $signature = 'tenders:purge {--dry-run : عرض العطاءات المستهدَفة دون حذفها}';

    protected $description = 'حذف العطاءات المؤرشفة منذ أكثر من 70 يوماً نهائياً مع مرفقاتها';

    public function handle(): int
    {
        $cutoff = now()->subDays(self::RETENTION_DAYS);

        $tenders = Tender::withTrashed()
            ->with('attachments')
            ->whereNotNull('archived_at')
            ->where('archived_at', '<', $cutoff)
            ->where(fn ($q) => $q->whereNull('deadline')->orWhere('deadline', '<', now()))
            ->orderBy('id')
            ->get();

        if ($this->option('dry-run')) {
            foreach ($tenders as $tender) {
                $this->line("#{$tender->id} {$tender->title} (أُرشف {$tender->archived_at->toDateString()})");
            }
            $this->info("سيتم حذف {$tenders->count()} عطاء مؤرشف منذ أكثر من " . self::RETENTION_DAYS . ' يوماً (dry-run — لم يُحذف شيء).');

            return self::SUCCESS;
        }

        $deleted = 0;

        foreach ($tenders as $tender) {
            $files = $tender->attachments->pluck('file_path')->filter()->all();
            $submissionFile = $tender->getRawOriginal('submission_file');
            if ($submissionFile && ! str_starts_with($submissionFile, 'http')) {
                $files[] = $submissionFile;
            }

            DB::transaction(fn () => $tender->forceDelete());

            // الملفات تُحذف بعد نجاح حذف الصف فقط — لو فشل الحذف لا نترك عطاءً بمرفقات مفقودة
            if ($files) {
                Storage::disk('public')->delete($files);
            }

            Log::channel('reminders')->info('tenders.purged_one', [
                'id'               => $tender->id,
                'reference_number' => $tender->reference_number,
                'title'            => $tender->title,
                'archived_at'      => $tender->archived_at?->toDateTimeString(),
                'deadline'         => $tender->deadline?->toDateTimeString(),
                'files_deleted'    => count($files),
            ]);

            $deleted++;
        }

        Log::channel('reminders')->info('tenders.purged', [
            'count'          => $deleted,
            'retention_days' => self::RETENTION_DAYS,
            'cutoff'         => $cutoff->toDateTimeString(),
        ]);
        $this->info("تم حذف {$deleted} عطاء مؤرشف منذ أكثر من " . self::RETENTION_DAYS . ' يوماً.');

        return self::SUCCESS;
    }
}
