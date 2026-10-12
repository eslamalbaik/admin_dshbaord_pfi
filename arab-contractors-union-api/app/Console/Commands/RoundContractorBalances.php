<?php

namespace App\Console\Commands;

use App\Models\Contractor;
use App\Services\BalanceRoundingService;
use App\Support\ContractorBalances;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * جبر كسور أرصدة كل المقاولين لدينار صحيح (لصالح الاتحاد) — على قيمة ذمة أو رصيد موجود.
 *
 * الـobserver يجبر الرصيد لحظياً بعد كل حركة؛ هذا الأمر للتطبيق الأول على البيانات
 * الموجودة، وكشبكة أمان يومية للتعديلات الجماعية التي لا تُطلق أحداث الموديل.
 */
class RoundContractorBalances extends Command
{
    protected $signature = 'balances:round
                            {--dry-run : عرض ما سيتغيّر بدون أي كتابة}';

    protected $description = 'جبر كسور أرصدة المقاولين لدينار صحيح لصالح الاتحاد (على آخر ذمة أو على الرصيد نفسه)';

    public function handle(BalanceRoundingService $service): int
    {
        $dry = (bool) $this->option('dry-run');
        $before = ContractorBalances::query()->pluck('net_exact_jod', 'contractors.id');

        DB::beginTransaction();
        try {
            $changed = $service->syncAll();
            $after = ContractorBalances::query()->pluck('net_exact_jod', 'contractors.id');
            $dry ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $names = Contractor::whereIn('id', array_keys($changed))->pluck('membership_number', 'id');
        $this->table(
            ['رقم العضوية', 'الصافي قبل', 'الجبر', 'الصافي بعد'],
            collect($changed)->map(fn ($fraction, $id) => [
                $names[$id] ?? $id,
                number_format((float) $before[$id], 2),
                $fraction > 0 ? "+{$fraction} على آخر ذمة" : ($fraction < 0 ? 'خصم ' . -$fraction . ' من الرصيد' : 'إلغاء الجبر'),
                number_format((float) $after[$id], 2),
            ])->values()->all(),
        );

        $this->info(($dry ? '[معاينة — لم يُكتب شيء] ' : '') . count($changed) . ' شركة تغيّر الجبر عندها.');

        return self::SUCCESS;
    }
}
