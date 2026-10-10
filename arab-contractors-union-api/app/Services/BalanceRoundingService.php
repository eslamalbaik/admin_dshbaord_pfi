<?php

namespace App\Services;

use App\Models\Contractor;
use App\Models\ContractorCredit;
use App\Models\ContractorDue;
use App\Support\ContractorBalances;

/**
 * جبر كسور رصيد المقاول لدينار صحيح لصالح الاتحاد — بالقيمة المخزَّنة، مش بالعرض فقط.
 *
 * - الشركة مطلوب منها (‎-991.40): ذمة "جبر كسور الرصيد" بالكسر (0.60) فيصير المطلوب 992.
 * - الشركة إلها رصيد (181.40): قيد رصيد دائن سالب بالكسر (‎-0.40) فيصير رصيدها 181.
 *
 * قيد الجبر "المفتوح" (ذمة ما انسدّ منها شيء، أو رصيد سالب ما استُخدم) يُعاد ضبطه بعد
 * كل تغيير على الذمم أو الدفعات أو الغرامات أو الأرصدة (BalanceRoundingObserver)، فيبقى
 * واحداً فقط لكل شركة. ذمة جبر انسدّ منها شيء صارت تاريخاً وما بتنلمس — يُحسب فوقها.
 */
class BalanceRoundingService
{
    public const TAG = 'جبر كسور الرصيد';

    /** منع الدوران: قيود الجبر نفسها ذمم/أرصدة تُطلق الـobserver */
    private static bool $syncing = false;

    public static function isSyncing(): bool
    {
        return self::$syncing;
    }

    /**
     * يضبط قيد الجبر المفتوح لشركة واحدة.
     *
     * @return float|null الكسر المسجَّل الآن (موجب = ذمة، سالب = خصم من الرصيد)، null إن لم يتغيّر شيء
     */
    public function sync(int $contractorId): ?float
    {
        if (self::$syncing) {
            return null;
        }

        self::$syncing = true;
        try {
            return $this->apply($contractorId);
        } finally {
            self::$syncing = false;
        }
    }

    /** @return array<int, float> contractor_id => الكسر الجديد، للشركات اللي تغيّر قيدها فقط */
    public function syncAll(): array
    {
        $changed = [];
        foreach (Contractor::query()->pluck('id') as $id) {
            if (($fraction = $this->sync($id)) !== null) {
                $changed[$id] = $fraction;
            }
        }

        return $changed;
    }

    private function apply(int $contractorId): ?float
    {
        $due = ContractorDue::where('contractor_id', $contractorId)
            ->where('notes', self::TAG)->where('paid_jod', 0)->first();
        $credit = ContractorCredit::where('contractor_id', $contractorId)
            ->where('notes', self::TAG)->where('used_jod', 0)->first();

        // الصافي بدون قيود الجبر المفتوحة، بالقروش حتى ما تدخل أخطاء الفاصلة العائمة
        $base = (int) round(ContractorBalances::exactNet($contractorId) * 100)
              + (int) round((float) ($due?->amount_jod ?? 0) * 100)
              + (int) round((float) ($credit?->amount_jod ?? 0) * -100);

        $fraction = (($base % 100) + 100) % 100; // floor(base) = base − fraction

        $wantDue    = $base < 0 ? $fraction : 0;
        $wantCredit = $base > 0 ? $fraction : 0;

        $current = [(int) round((float) ($due?->amount_jod ?? 0) * 100), (int) round((float) ($credit?->amount_jod ?? 0) * -100)];
        if ($current === [$wantDue, $wantCredit]) {
            return null;
        }

        $this->put($due, $wantDue, fn () => ContractorDue::create([
            'contractor_id' => $contractorId,
            'description'   => self::TAG,
            'amount_jod'    => $wantDue / 100,
            'paid_jod'      => 0,
            'status'        => 'unpaid',
            'source'        => 'manual',
            'notes'         => self::TAG,
        ]), ['amount_jod' => $wantDue / 100]);

        $this->put($credit, $wantCredit, fn () => ContractorCredit::create([
            'contractor_id' => $contractorId,
            'description'   => self::TAG,
            'amount_jod'    => -$wantCredit / 100,
            'used_jod'      => 0,
            'source'        => 'manual',
            'notes'         => self::TAG,
        ]), ['amount_jod' => -$wantCredit / 100]);

        return ($wantDue - $wantCredit) / 100;
    }

    /**
     * ينشئ أو يعدّل أو يحذف قيداً مفتوحاً واحداً حسب القيمة المطلوبة بالقروش.
     * الحذف ناعم عمداً: payment_allocations مربوطة بـcascadeOnDelete، فالحذف النهائي لذمة جبر
     * انعكست دفعتها كان سيمسح سجل توزيع الدفعات معها بصمت.
     */
    private function put(?object $row, int $cents, callable $create, array $update): void
    {
        if ($cents === 0) {
            $row?->delete();
        } elseif ($row) {
            $row->update($update);
        } else {
            $create();
        }
    }
}
