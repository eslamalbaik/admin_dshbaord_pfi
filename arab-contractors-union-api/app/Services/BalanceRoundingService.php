<?php

namespace App\Services;

use App\Models\Contractor;
use App\Models\ContractorCredit;
use App\Models\ContractorDue;
use App\Models\Payment;
use App\Support\ContractorBalances;

/**
 * جبر كسور رصيد المقاول لدينار صحيح لصالح الاتحاد — بالقيمة المخزَّنة، مش بالعرض فقط.
 *
 * الكسر بينضاف على قيمة سطر موجود، مش كقيد أو حركة لحالها (طلب eslam 12/10):
 * - الشركة مطلوب منها (‎-991.40): آخر ذمة مفتوحة بتصير 992 بدل 991.40.
 * - الشركة إلها رصيد (181.40): رصيدها الدائن (أو فائض دفعتها) بيصير 181.
 * rounding_jod على السطر بيحفظ قدّيش من قيمته جبر، فبينعاد ضبطه بعد كل تغيير على الذمم أو
 * الدفعات أو الغرامات أو الأرصدة (BalanceRoundingObserver) وبيضل سطر جبر واحد لكل شركة.
 * ذمة انسدّ من جزء الجبر فيها شيء صارت تاريخاً وما بتنلمس — يُحسب فوقها.
 *
 * قيد "جبر كسور الرصيد" المنفصل (الشكل القديم) بيتحوّل للشكل الجديد بأول ضبط، وبيضل يُستخدم
 * بس لشركة عليها غرامات وما عندها ولا ذمة ينضاف عليها الكسر.
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
        // قيود الجبر المفتوحة الحالية: القديمة المنفصلة + السطور اللي عليها rounding_jod
        $legacyDue = ContractorDue::where('contractor_id', $contractorId)
            ->where('notes', self::TAG)->where('paid_jod', 0)->first();
        $legacyCredit = ContractorCredit::where('contractor_id', $contractorId)
            ->where('notes', self::TAG)->where('used_jod', 0)->first();
        // ذمة لسا ما انسدّ من جزء الجبر فيها شيء (الجبر آخر شي بينسدّ من الذمة)
        $dueHosts = ContractorDue::where('contractor_id', $contractorId)->where('rounding_jod', '>', 0)
            ->whereRaw('paid_jod <= amount_jod - rounding_jod')->orderByDesc('id')->get();
        $creditHosts = ContractorCredit::where('contractor_id', $contractorId)->where('rounding_jod', '>', 0)->get();
        $paymentHosts = Payment::where('contractor_id', $contractorId)->where('rounding_jod', '>', 0)->get();

        // دفعة رجعت لقيد المراجعة أو انرفضت: رصيدها مش محسوب أصلاً، فالكسر اللي عليها بيسقط
        foreach ($paymentHosts->where('status', '!=', 'paid') as $payment) {
            $this->setPaymentRounding($payment, 0);
        }
        $paymentHosts = $paymentHosts->where('status', 'paid');

        $cents = fn ($v) => (int) round((float) $v * 100);
        $current = [
            $cents($legacyDue?->amount_jod ?? 0) + $dueHosts->sum(fn ($d) => $cents($d->rounding_jod)),
            -$cents($legacyCredit?->amount_jod ?? 0)
                + $creditHosts->sum(fn ($c) => $cents($c->rounding_jod))
                + $paymentHosts->sum(fn ($p) => $cents($p->rounding_jod)),
        ];

        // الصافي بدون قيود الجبر المفتوحة، بالقروش حتى ما تدخل أخطاء الفاصلة العائمة
        $base = $cents(ContractorBalances::exactNet($contractorId)) + $current[0] + $current[1];

        $fraction = (($base % 100) + 100) % 100; // floor(base) = base − fraction

        if ($base > 0) {
            $wantDue    = 0;
            $wantCredit = $fraction;
        } else {
            // كسر رصيد انشطب لصالح الاتحاد (فرق عملة مثلاً) بيضل مشطوب لما الشركة تصير مدينة —
            // ما بيرجع ينصرف على الذمة الجديدة ثم ينحسب عليها كجبر. الجزء المشطوب محجوز بسطره
            // (منقوص من الرصيد أو محسوب مستخدَم من الدفعة) فما في خطر ينصرف.
            $wantCredit = min($current[1], $fraction);
            $wantDue    = $fraction - $wantCredit;
        }

        $legacy = $legacyDue || $legacyCredit
            || $dueHosts->count() > 1 || $creditHosts->count() + $paymentHosts->count() > 1;
        $misplaced = $wantDue > 0 && $dueHosts->isNotEmpty()
            && ($target = $this->dueTargets($contractorId)[0]) && $target->id !== $dueHosts->first()->id;
        if (! $legacy && ! $misplaced && $current === [$wantDue, $wantCredit]) {
            return null;
        }

        // القديم المنفصل بينحذف (ناعم: payment_allocations مربوطة بـcascadeOnDelete)، وبيتحوّل للشكل الجديد
        $legacyDue?->delete();
        $legacyCredit?->delete();

        $this->placeDue($contractorId, $dueHosts, $wantDue);
        $this->placeCredit($contractorId, $creditHosts, $paymentHosts, $wantCredit);

        return ($wantDue - $wantCredit) / 100;
    }

    /** الكسر المطلوب على الذمم: بيضل على نفس الذمة لو موجودة، وإلا على آخر ذمة بينسدّ */
    private function placeDue(int $contractorId, $hosts, int $cents): void
    {
        $host = $hosts->first();
        foreach ($hosts->slice(1) as $extra) {
            $this->setDueRounding($extra, 0);
        }

        if ($cents === 0) {
            $host && $this->setDueRounding($host, 0);

            return;
        }

        // لو انضافت ذمة أحدث، الكسر بينتقل عليها وبترجع الذمة القديمة لقيمتها الأصلية
        [$target, $fallback] = $this->dueTargets($contractorId);
        if ($target && $host && $target->id !== $host->id) {
            $this->setDueRounding($host, 0);
            $host = $target->fresh();
        }
        $host ??= $target ?? $fallback;

        if (! $host) {
            // ما في ولا ذمة (الدين كله غرامات): قيد منفصل كالسابق
            ContractorDue::create([
                'contractor_id' => $contractorId,
                'description'   => self::TAG,
                'amount_jod'    => $cents / 100,
                'paid_jod'      => 0,
                'status'        => 'unpaid',
                'source'        => 'manual',
                'notes'         => self::TAG,
            ]);

            return;
        }

        $this->setDueRounding($host, $cents);
    }

    /** الكسر المشطوب من الرصيد: على نفس السطر لو بيكفي، وإلا على أحدث رصيد دائن أو فائض دفعة بيكفي */
    private function placeCredit(int $contractorId, $creditHosts, $paymentHosts, int $cents): void
    {
        foreach ($creditHosts as $credit) {
            $this->setCreditRounding($credit, 0);
        }
        foreach ($paymentHosts as $payment) {
            $this->setPaymentRounding($payment, 0);
        }

        if ($cents === 0) {
            return;
        }

        $fits = fn ($available) => (int) round($available * 100) >= $cents;
        $prefer = $creditHosts->concat($paymentHosts)->first();

        $host = $prefer && $fits($this->available($prefer->fresh())) ? $prefer->fresh() : null;
        $host ??= ContractorCredit::where('contractor_id', $contractorId)
            ->where(fn ($q) => $q->whereNull('notes')->orWhere('notes', '!=', self::TAG))
            ->orderByDesc('id')->get()->first(fn ($c) => $fits($this->available($c)));
        $host ??= Payment::where('contractor_id', $contractorId)->where('status', 'paid')
            ->whereIn('type', Payment::CREDIT_TYPES)
            ->orderByRaw('COALESCE(paid_at, created_at) DESC')->orderByDesc('id')
            ->get()->first(fn ($p) => $fits($this->available($p)));

        if ($host instanceof ContractorCredit) {
            $this->setCreditRounding($host, $cents);
        } elseif ($host instanceof Payment) {
            $this->setPaymentRounding($host, $cents);
        } else {
            // الرصيد موزَّع على كذا سطر وولا واحد لحاله بيكفي الكسر: قيد منفصل كالسابق
            ContractorCredit::create([
                'contractor_id' => $contractorId,
                'description'   => self::TAG,
                'amount_jod'    => -$cents / 100,
                'used_jod'      => 0,
                'source'        => 'manual',
                'notes'         => self::TAG,
            ]);
        }
    }

    /**
     * [آخر ذمة مفتوحة بترتيب التسديد (الأقدم أولاً) — يعني الكسر آخر شي بينسدّ،
     *  آخر ذمة مهما كانت حالتها لو ما في مفتوحة]
     */
    private function dueTargets(int $contractorId): array
    {
        $real = ContractorDue::where('contractor_id', $contractorId)
            ->where(fn ($q) => $q->whereNull('notes')->orWhere('notes', '!=', self::TAG))->get();

        return [
            ContractorDue::sortOldestFirst($real->where('status', '!=', 'paid'))->last(),
            ContractorDue::sortOldestFirst($real)->last(),
        ];
    }

    private function available(ContractorCredit|Payment $row): float
    {
        return $row instanceof Payment
            ? round((float) $row->amount_jod - (float) $row->used_amount_jod, 2)
            : round((float) $row->amount_jod - (float) $row->used_jod, 2);
    }

    private function setDueRounding(ContractorDue $due, int $cents): void
    {
        $amount = round((float) $due->amount_jod - (float) $due->rounding_jod + $cents / 100, 2);
        $paid   = round((float) $due->paid_jod, 2);

        $due->update([
            'amount_jod'   => $amount,
            'rounding_jod' => $cents / 100,
            'status'       => $paid >= $amount && $paid > 0 ? 'paid' : ($paid > 0 ? 'partially_paid' : 'unpaid'),
        ]);
    }

    private function setCreditRounding(ContractorCredit $credit, int $cents): void
    {
        $credit->update([
            'amount_jod'   => round((float) $credit->amount_jod + (float) $credit->rounding_jod - $cents / 100, 2),
            'rounding_jod' => $cents / 100,
        ]);
    }

    private function setPaymentRounding(Payment $payment, int $cents): void
    {
        $payment->update([
            'used_amount_jod' => round(max(0, (float) $payment->used_amount_jod - (float) $payment->rounding_jod + $cents / 100), 2),
            'rounding_jod'    => $cents / 100,
        ]);
    }
}
