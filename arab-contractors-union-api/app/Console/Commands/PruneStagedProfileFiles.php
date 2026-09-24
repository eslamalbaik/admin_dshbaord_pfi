<?php

namespace App\Console\Commands;

use App\Models\ProfileUpdateRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * تنظيف مستندات طلبات تعديل الملف المرحَّلة التي لم يبقَ لها معنى (TASK-17 US11).
 *
 * الموافقة تنقل الملف والرفض والإلغاء يحذفانه، فالمسار الطبيعي لا يترك شيئاً. لكن ما يبقى
 * هو الحالات الشاذّة: صف حُذف من القاعدة مباشرة، أو نقل فشل نصفه، أو طلب قديم بُتّ فيه قبل
 * أن يوجد هذا التنظيف. بدون هذا الأمر تتراكم تلك الملفات إلى ما لا نهاية — وهو البند الذي
 * يُنسى عادة ثم يظهر بعد سنة كقرص ممتلئ.
 *
 * محافظ عن قصد: لا يحذف إلا ملفاً لا يعود لأي طلب **معلّق**، وأقدم من مدة الاحتفاظ.
 */
class PruneStagedProfileFiles extends Command
{
    protected $signature = 'profile-requests:prune-staged {--dry-run : عرض ما سيُحذف دون حذف}';

    protected $description = 'حذف مستندات طلبات تعديل الملف المرحَّلة التي لم تبقَ معلّقة';

    private const STAGING_DIR = 'contractors/profile-update/staged';

    public function handle(): int
    {
        $dryRun    = (bool) $this->option('dry-run');
        $retention = (int) config('profile.staged_files_retention_days', 30);
        $cutoff    = now()->subDays($retention)->getTimestamp();
        $disk      = Storage::disk('public');

        // كل مسار مرحَّل تعود ملكيته لطلب معلّق — هذه لا تُلمس مهما كان عمرها
        $protected = ProfileUpdateRequest::where('status', 'pending')
            ->whereNotNull('proposed_files')
            ->pluck('proposed_files')
            ->flatMap(fn ($files) => array_values((array) $files))
            ->unique()
            ->all();

        $deleted = 0;
        $kept    = 0;

        foreach ($disk->allFiles(self::STAGING_DIR) as $path) {
            if (in_array($path, $protected, true)) {
                $kept++;
                continue;
            }

            if ($disk->lastModified($path) > $cutoff) {
                $kept++;
                continue;
            }

            $this->line(($dryRun ? '[معاينة] ' : '') . "حذف: {$path}");

            if (! $dryRun) {
                $disk->delete($path);
            }

            $deleted++;
        }

        $this->info($dryRun
            ? "معاينة: {$deleted} ملفاً سيُحذف، {$kept} ملفاً يُحتفظ به."
            : "تم حذف {$deleted} ملفاً مرحَّلاً، وأُبقي على {$kept}.");

        return self::SUCCESS;
    }
}
