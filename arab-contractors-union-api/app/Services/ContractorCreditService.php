<?php

namespace App\Services;

use App\Models\Contractor;
use App\Models\ContractorCredit;
use App\Models\ContractorDue;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Penalty;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * صرف الرصيد الدائن للمقاول تلقائياً على ذممه وغراماته المفتوحة.
 *
 * قبل هيك لما تنضاف ذمة جديدة لمقاول عنده رصيد سابق (فائض دفعة أو دفعة مقدمة أو رصيد من كشف
 * الدفعات)، الصافي بصفحة الأرصدة كان صح بس الذمة تضل "غير مسدَّدة" والرصيد يضل "له". هلأ الرصيد
 * بينصرف عليها فوراً: الذمة بتصير مسدَّدة كلياً أو جزئياً، وكل تسديد بينسجّل بـpayment_allocations
 * (من دفعة أو من رصيد دائن) حتى يبيّن بسجل المدفوعات وينعكس لو الدفعة رجعت.
 *
 * الترتيب: أقدم الذمم أولاً (حسب السنة)، بعدين الغرامات المفتوحة. مصادر الرصيد: فائض الدفعات
 * المؤكَّدة (الأقدم أولاً) ثم الأرصدة الدائنة.
 */
class ContractorCreditService
{
    /** @return float المبلغ اللي انصرف بالدينار */
    public function applyAvailableCredit(Contractor $contractor, ?User $by = null): float
    {
        return DB::transaction(function () use ($contractor, $by) {
            $sources = $this->creditSources($contractor);
            if ($sources->isEmpty()) {
                return 0.0;
            }

            $targets = $this->openTargets($contractor);
            $applied = 0.0;

            foreach ($targets as $target) {
                $need = $target instanceof ContractorDue ? $target->remaining_jod : $target->remaining;

                while ($need > 0 && $sources->isNotEmpty()) {
                    $source    = $sources->first();
                    $available = $this->available($source);
                    $amount    = round(min($need, $available), 2);

                    if ($amount > 0) {
                        $this->spend($source, $target, $amount, $by);
                        $need    = round($need - $amount, 2);
                        $applied = round($applied + $amount, 2);
                    }

                    if ($this->available($source) <= 0) {
                        $sources->shift();
                    }
                }

                if ($sources->isEmpty()) {
                    break;
                }
            }

            return $applied;
        });
    }

    /** الرصيد الدائن المتاح للمقاول بالدينار (نفس "له" بصفحة الأرصدة) */
    public function availableCredit(Contractor $contractor): float
    {
        return round($this->creditSources($contractor)->sum(fn ($s) => $this->available($s)), 2);
    }

    private function creditSources(Contractor $contractor): Collection
    {
        $payments = Payment::where('contractor_id', $contractor->id)
            ->where('status', 'paid')
            ->whereIn('type', Payment::CREDIT_TYPES)
            ->whereRaw('amount_jod - COALESCE(used_amount_jod, 0) > 0')
            ->orderByRaw('COALESCE(paid_at, created_at)')->orderBy('id')
            ->lockForUpdate()->get();

        $credits = ContractorCredit::where('contractor_id', $contractor->id)
            ->whereRaw('amount_jod - used_jod > 0')
            ->orderBy('id')->lockForUpdate()->get();

        return $payments->concat($credits)->values();
    }

    private function openTargets(Contractor $contractor): Collection
    {
        $dues = $contractor->dues()->outstanding()
            ->orderByRaw('year IS NULL, year asc')->orderBy('id')->lockForUpdate()->get();

        $penalties = $contractor->penalties()->whereIn('status', ['unpaid', 'partially_paid'])
            ->orderBy('created_at')->orderBy('id')->lockForUpdate()->get();

        return $dues->concat($penalties);
    }

    private function available(Payment|ContractorCredit $source): float
    {
        return $source instanceof Payment
            ? round((float) $source->amount_jod - (float) $source->used_amount_jod, 2)
            : round((float) $source->amount_jod - (float) $source->used_jod, 2);
    }

    private function spend(Payment|ContractorCredit $source, ContractorDue|Penalty $target, float $amount, ?User $by): void
    {
        if ($source instanceof Payment) {
            $source->used_amount_jod = round((float) $source->used_amount_jod + $amount, 2);
            $source->save();
        } else {
            $source->used_jod = round((float) $source->used_jod + $amount, 2);
            $source->save();
        }

        $target->applyPayment($amount);

        // رسوم الاشتراك السنوي انسدّت كاملة من الرصيد ← العضوية بتتفعّل لسنتها لحالها
        if ($target instanceof ContractorDue && $target->is_membership_fee && $target->status === 'paid') {
            app(MembershipRenewalService::class)->activateForDue($target, $by?->id);
        }

        if ($source instanceof Payment) {
            $target instanceof ContractorDue
                ? PaymentAllocation::record($source, $target, $amount)
                : PaymentAllocation::recordPenalty($source, $target, $amount);
        } else {
            PaymentAllocation::recordFromCredit($source, $target, $amount);
        }

        AuditLogService::record(
            $by,
            $target instanceof ContractorDue ? 'due.settled_from_credit' : 'penalty.settled_from_credit',
            $target,
            [
                'contractor_id' => $target->contractor_id,
                'amount_jod'    => $amount,
                'payment_id'    => $source instanceof Payment ? $source->id : null,
                'credit_id'     => $source instanceof ContractorCredit ? $source->id : null,
            ],
        );
    }
}
