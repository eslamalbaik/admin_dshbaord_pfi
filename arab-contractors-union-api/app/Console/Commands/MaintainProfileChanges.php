<?php

namespace App\Console\Commands;

use App\Models\ProfileChange;
use App\Models\User;
use App\Notifications\ProfileChangesOverdueNotification;
use App\Support\ProfileReview;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * صيانة يومية لطلبات تعديل الملفات:
 *   1. تذكير المراجعين بالطلبات اللي تعدّت مهلة المراجعة (أيام عمل).
 *   2. حذف الملفات المؤقتة اللي ما إلها طلب معلّق (أقدم من يوم) — حتى ما يتعبّى القرص.
 */
class MaintainProfileChanges extends Command
{
    protected $signature = 'profile-changes:maintain {--dry-run : عرض بدون حذف أو إشعار}';

    protected $description = 'تذكير بطلبات تعديل الملفات المتأخرة وتنظيف ملفاتها المؤقتة';

    public function handle(): int
    {
        $sla = (int) config('profile_review.sla_working_days');

        $overdue = ProfileChange::pending()
            ->where('mode', ProfileChange::MODE_PRE)
            ->get(['contractor_id', 'created_at'])
            ->groupBy('contractor_id')
            ->filter(fn ($items) => ProfileReview::workingDaysSince($items->min('created_at')) >= $sla)
            ->count();

        $this->info("طلبات متأخرة: {$overdue}");

        if ($overdue && ! $this->option('dry-run')) {
            $recipients = User::dashboardRecipients('contractors.profile_changes');
            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new ProfileChangesOverdueNotification($overdue, $sla));
            }
        }

        // الملفات المؤقتة اليتيمة
        $disk = Storage::disk('public');
        $dir = config('profile_review.staging_dir');
        $referenced = ProfileChange::pending()->where('kind', 'file')->pluck('new_value')->flip();
        $deleted = 0;

        foreach ($disk->allFiles($dir) as $path) {
            if (isset($referenced[$path]) || $disk->lastModified($path) > now()->subDay()->getTimestamp()) {
                continue;
            }

            if (! $this->option('dry-run')) {
                $disk->delete($path);
            }
            $deleted++;
        }

        $this->info("ملفات مؤقتة محذوفة: {$deleted}");

        return self::SUCCESS;
    }
}
