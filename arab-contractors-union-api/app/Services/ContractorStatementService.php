<?php

namespace App\Services;

use App\Http\Controllers\Api\ContractorBalanceController;
use App\Models\Contractor;
use App\Models\ContractorCredit;
use App\Models\ContractorDue;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Penalty;
use Illuminate\Support\Collection;

/**
 * سجل المدفوعات التفصيلي لشركة واحدة (كشف حساب): كل ذمة وغرامة عليها، كل دفعة ورصيد لها،
 * وين انصرف كل مبلغ، والرصيد بعد كل حركة. الرصيد الأخير = صافي صفحة الأرصدة بالظبط.
 *
 * كيف بتنحسب حركة الرصيد (balance_after_jod):
 * - ذمة ← عليه amount_jod (بعد الخصم). غرامة غير مرفوضة ← عليه amount.
 * - دفعة مؤكَّدة من الأنواع اللي فائضها رصيد (Payment::CREDIT_TYPES) ← له مبلغها.
 * - دفعة مؤكَّدة غيرها (رسوم عضوية، باقة معدات) ← له بس الجزء اللي انصرف على ذمم/غرامات،
 *   الباقي راح على العضوية/الباقة نفسها.
 * - رصيد دائن (contractor_credits) ← له مبلغه.
 * - تسديد قديم ما إله سجل توزيع (قبل 6/10/2026 أو تسوية يدوية) ← حركة "مسدَّد سابقاً" له.
 * - دفعة قيد المراجعة أو مرفوضة ← بتنعرض بدون ما تحرّك الرصيد.
 */
class ContractorStatementService
{
    public function build(Contractor $contractor): array
    {
        $dues      = $contractor->dues()->get();
        $penalties = $contractor->penalties()->get();
        $payments  = $contractor->payments()->with([...Payment::TITLE_RELATIONS])->get();
        $credits   = ContractorCredit::where('contractor_id', $contractor->id)->get();

        $allocations = PaymentAllocation::query()
            ->where(fn ($q) => $q->whereIn('contractor_due_id', $dues->pluck('id'))
                ->orWhereIn('penalty_id', $penalties->pluck('id')))
            ->get();

        $entries = collect();

        foreach ($dues as $due) {
            $entries->push($this->entry(
                date: $due->created_at,
                kind: 'due',
                id: $due->id,
                title: $due->title . ($due->year && ! str_contains($due->title, (string) $due->year) ? " ({$due->year})" : ''),
                reference: $due->reference_number,
                amount: (float) $due->amount_jod,
                delta: -(float) $due->amount_jod,
                status: $due->status,
                statusLabel: $due->status_label,
                extra: [
                    'paid_jod'      => round((float) $due->paid_jod, 2),
                    'remaining_jod' => $due->remaining_jod,
                    'due_date'      => $due->due_date?->toDateString(),
                    'year'          => $due->year,
                ],
            ));

            $this->pushLegacySettlement($entries, $due->updated_at, 'due', $due->id, $due->title, $due->reference_number,
                (float) $due->paid_jod - (float) $allocations->where('contractor_due_id', $due->id)->sum('amount_jod'));
        }

        foreach ($penalties as $penalty) {
            $counted = $penalty->status === 'rejected' ? 0.0 : (float) $penalty->amount;

            $entries->push($this->entry(
                date: $penalty->created_at,
                kind: 'penalty',
                id: $penalty->id,
                title: $penalty->reason ?: 'غرامة',
                reference: null,
                amount: (float) $penalty->amount,
                delta: -$counted,
                status: $penalty->status,
                statusLabel: $penalty->status_label,
                extra: [
                    'paid_jod'      => round((float) $penalty->paid_amount, 2),
                    'remaining_jod' => $penalty->remaining,
                ],
            ));

            if ($penalty->status !== 'rejected') {
                $this->pushLegacySettlement($entries, $penalty->paid_at ?? $penalty->updated_at, 'penalty', $penalty->id,
                    $penalty->reason ?: 'غرامة', null,
                    (float) $penalty->paid_amount - (float) $allocations->where('penalty_id', $penalty->id)->sum('amount_jod'));
            }
        }

        foreach ($payments as $payment) {
            $entries->push($this->paymentEntry($payment));
        }

        foreach ($credits as $credit) {
            $allocated = (float) $credit->allocations()->sum('amount_jod');
            $untracked = max(0, (float) $credit->used_jod - $allocated);

            $entries->push($this->entry(
                date: $credit->created_at,
                kind: 'credit',
                id: $credit->id,
                title: $credit->description ?: 'رصيد دائن',
                reference: null,
                amount: (float) $credit->amount_jod,
                delta: (float) $credit->amount_jod - $untracked,
                status: 'paid',
                statusLabel: 'رصيد دائن',
                extra: [
                    'used_jod'   => round((float) $credit->used_jod, 2),
                    'unused_jod' => round((float) $credit->amount_jod - (float) $credit->used_jod, 2),
                    'allocations' => $credit->allocations()->with(['due', 'penalty'])->get()
                        ->map(fn ($a) => $this->allocationRow($a))->values(),
                ],
            ));
        }

        // أقدم حركة أولاً لحساب الرصيد التراكمي، وبعدين الأحدث أولاً للعرض
        $balance = 0.0;
        $entries = $entries
            ->sortBy(fn ($e) => [$e['date_sort'], $e['kind_order'], $e['id']])
            ->values()
            ->map(function ($e) use (&$balance) {
                $balance = round($balance + $e['delta'], 2);
                $e['balance_after_jod'] = $balance;
                unset($e['date_sort'], $e['kind_order'], $e['delta']);

                return $e;
            })
            ->reverse()
            ->values();

        return [
            'contractor' => [
                'id'                => $contractor->id,
                'name'              => $contractor->name,
                'membership_number' => $contractor->membership_number,
            ],
            'summary'    => $this->summary($contractor, $dues, $penalties, $payments),
            'entries'    => $entries,
        ];
    }

    private function summary(Contractor $contractor, Collection $dues, Collection $penalties, Collection $payments): array
    {
        $row = app(ContractorBalanceController::class)->balancesQuery()->where('contractors.id', $contractor->id)->first();

        $net = round((float) $row->net_jod, 2);

        return [
            'total_dues_jod'          => round((float) $dues->sum('amount_jod'), 2),
            'total_penalties_jod'     => round((float) $penalties->where('status', '!=', 'rejected')->sum('amount'), 2),
            'total_paid_jod'          => round((float) $payments->where('status', 'paid')->sum(fn ($p) => (float) ($p->amount_jod ?? $p->amount)), 2),
            'pending_payments_jod'    => round((float) $payments->where('status', 'pending')->sum('amount'), 2),
            'dues_remaining_jod'      => round((float) $row->dues_jod, 2),
            'penalties_remaining_jod' => round((float) $row->penalties_jod, 2),
            'credit_jod'              => round((float) $row->credit_jod, 2),
            // المتبقي المطلوب بعد خصم الرصيد (صفر لو ما عليه)، والصافي بإشارته (سالب = عليه)
            'amount_due_jod'          => max(0.0, round(-$net, 2)),
            'net_jod'                 => $net,
            'position'                => $net > 0 ? 'credit' : ($net < 0 ? 'owes' : 'settled'),
            'currency'                => 'JOD',
        ];
    }

    private function paymentEntry(Payment $payment): array
    {
        $amountJod = (float) ($payment->amount_jod ?? $payment->amount);
        $allocated = (float) $payment->allocations->sum('amount_jod');

        if ($payment->status !== 'paid') {
            $delta = 0.0;
        } elseif (in_array($payment->type, Payment::CREDIT_TYPES, true)) {
            // تسديد قديم بدون سجل توزيع بينعرض من جهة الذمة ("مسدَّد سابقاً")، فما بنعدّه مرتين
            $delta = $amountJod - max(0, (float) $payment->used_amount_jod - $allocated);
        } else {
            $delta = $allocated;
        }

        return $this->entry(
            date: $payment->paid_at ?? $payment->submitted_at ?? $payment->created_at,
            kind: 'payment',
            id: $payment->id,
            title: $payment->title,
            reference: $payment->transaction_number,
            amount: $amountJod,
            delta: $delta,
            status: $payment->status,
            statusLabel: $payment->status_label,
            extra: [
                'type'                  => $payment->type,
                'type_label'            => $payment->type_label,
                'bank_reference_number' => $payment->reference_number,
                'amount'                => $payment->amount,
                'currency'              => $payment->currency ?? 'JOD',
                'notes'                 => $payment->notes,
                'used_jod'              => round((float) $payment->used_amount_jod, 2),
                'unused_jod'            => $payment->status === 'paid' && in_array($payment->type, Payment::CREDIT_TYPES, true)
                    ? round($amountJod - (float) $payment->used_amount_jod, 2) : 0.0,
                'rejection_reason'      => $payment->rejection_reason,
                'allocations'           => $payment->allocations->map(fn ($a) => $this->allocationRow($a))->values(),
            ],
        );
    }

    private function allocationRow(PaymentAllocation $allocation): array
    {
        return [
            'kind'             => $allocation->penalty_id ? 'penalty' : 'due',
            'id'               => $allocation->penalty_id ?? $allocation->contractor_due_id,
            'title'            => $allocation->penalty_id
                ? ($allocation->penalty?->reason ?: 'غرامة')
                : ($allocation->due?->title ?? 'ذمة مالية'),
            'reference_number' => $allocation->due?->reference_number,
            'amount_jod'       => round((float) $allocation->amount_jod, 2),
        ];
    }

    private function pushLegacySettlement(Collection $entries, $date, string $kind, int $id, string $title, ?string $reference, float $amount): void
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            return;
        }

        $entries->push($this->entry(
            date: $date,
            kind: 'settlement',
            id: $id,
            title: "تسديد سابق: {$title}",
            reference: $reference,
            amount: $amount,
            delta: $amount,
            status: 'paid',
            statusLabel: 'مسدَّد سابقاً',
            extra: ['settles' => ['kind' => $kind, 'id' => $id]],
        ));
    }

    private const KIND_ORDER = ['due' => 0, 'penalty' => 1, 'credit' => 2, 'payment' => 3, 'settlement' => 4];

    private function entry($date, string $kind, int $id, string $title, ?string $reference, float $amount, float $delta,
        string $status, string $statusLabel, array $extra = []): array
    {
        return [
            'kind'             => $kind,
            'id'               => $id,
            'date'             => $date?->toDateString(),
            'title'            => $title,
            'reference_number' => $reference,
            // عليه = ذمة/غرامة، له = دفعة/رصيد/تسديد
            'direction'        => in_array($kind, ['due', 'penalty'], true) ? 'debit' : 'credit',
            'amount_jod'       => round($amount, 2),
            'status'           => $status,
            'status_label'     => $statusLabel,
            'counts_in_balance' => round($delta, 2) != 0.0,
            'date_sort'        => $date?->getTimestamp() ?? 0,
            'kind_order'       => self::KIND_ORDER[$kind],
            'delta'            => round($delta, 2),
        ] + $extra;
    }
}
