<?php

namespace App\Console\Commands;

use App\Models\Contractor;
use App\Services\DuesGenerationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * يولّد رسوم الاشتراك السنوية تلقائياً كل 1/1 (قرار الإدارة 2026-10-05): حالة العضوية بصفحتي
 * الأرصدة والمقاولين صارت حسب الرصيد بس، فلازم ذمة السنة الجديدة تنزل بأول يوم، وإلا كل
 * اللي رصيده صفر بيضل "فعّال" لحد ما حدا يكبس "توليد الرسوم".
 *
 * نفس محرّك صفحة الذمم (generateFeeBulk)، على كل المقاولين بغض النظر عن حالتهم الإدارية
 * (نشط/معلّق/منتهي/موقوف — قرار الإدارة 2026-10-05: العضوية بتنولّد بكل الحالات)، ومستثنى منهم
 * اللي عنده عضوية مدفوعة مسبقاً بتغطي السنة، حتى ما تنحسب عليه السنة مرتين. المكرّر لنفس
 * السنة بيتخطّاه المحرّك أصلاً، فإعادة التشغيل آمنة.
 */
class GenerateAnnualDues extends Command
{
    protected $signature = 'dues:generate-annual {--year= : السنة (افتراضياً السنة الحالية)} {--dry-run}';

    protected $description = 'توليد رسوم الاشتراك السنوية لكل المقاولين';

    public function handle(DuesGenerationService $service): int
    {
        $year = (int) ($this->option('year') ?: now()->year);

        $ids = Contractor::query()
            ->whereNotNull('specialties')
            ->whereDoesntHave('memberships', fn ($q) => $q
                ->where('status', 'active')
                ->whereNotNull('expires_at')
                ->whereYear('expires_at', '>=', $year))
            ->pluck('id')
            ->all();

        // مصفوفة فاضية عند generateFeeBulk معناها "كل المقاولين"، فلازم نوقف هون
        if ($ids === []) {
            $this->info("ما في مقاولين بحاجة رسوم {$year}.");

            return self::SUCCESS;
        }

        $result = $service->generateFeeBulk($ids, $year, (bool) $this->option('dry-run'), null);

        Log::channel('finance')->info('dues.annual_generated', [
            'year'          => $year,
            'dry_run'       => (bool) $this->option('dry-run'),
            'created'       => $result['created_count'],
            'skipped'       => count($result['would_skip_existing']),
            'unresolvable'  => count($result['unresolvable']),
        ]);

        $this->info("رسوم {$year}: انعمل {$result['created_count']}، تخطّى "
            . count($result['would_skip_existing']) . ' موجودة، و'
            . count($result['unresolvable']) . ' ما انحسبت رسومهم.');

        return self::SUCCESS;
    }
}
