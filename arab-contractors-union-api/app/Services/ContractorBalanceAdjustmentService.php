<?php

namespace App\Services;

use App\Http\Controllers\Api\ContractorBalanceController;
use App\Models\Contractor;
use App\Models\ContractorBalanceAdjustment;
use App\Models\ContractorCredit;
use App\Models\ContractorDue;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * تعديل رصيد مقاول يدوياً من صفحة "أرصدة المقاولين".
 *
 * الرصيد الصافي محسوب لحظياً (له − عليه)، فالتعديل ما بيكتب رقم فوقه، بل بيضيف حركة:
 * - زيادة ← رصيد دائن (contractor_credits) بقيمة الفرق، وبينصرف فوراً على الذمم المفتوحة إن وجدت.
 * - إنقاص ← ذمة "تعديل رصيد يدوي" بقيمة الفرق، والرصيد الدائن الموجود بينصرف عليها.
 * - تحديد رصيد جديد ← الفرق بين الهدف والرصيد الحالي، وبعدين زيادة أو إنقاص حسب إشارته.
 *
 * الدفعات والذمم الأصلية ما بتنلمس، وكل تعديل بينحفظ بـcontractor_balance_adjustments
 * وبسجل المحددات الهامة مع الرصيد وحالة العضوية قبل/بعد والسبب.
 */
class ContractorBalanceAdjustmentService
{
    public const DESCRIPTION = 'تعديل رصيد يدوي';

    public function __construct(
        private ContractorBalanceController $balances,
        private ContractorCreditService $credits,
    ) {}

    public function adjust(Contractor $contractor, string $mode, float $amount, string $reason, ?User $by): ContractorBalanceAdjustment
    {
        return DB::transaction(function () use ($contractor, $mode, $amount, $reason, $by) {
            Contractor::whereKey($contractor->id)->lockForUpdate()->first();

            [$before, $statusBefore] = $this->current($contractor->id);

            $delta = round(match ($mode) {
                'increase' => abs($amount),
                'decrease' => -abs($amount),
                'set'      => $amount - $before,
            }, 2);

            if (abs($delta) < 0.01) {
                throw ValidationException::withMessages([
                    'amount' => 'الرصيد الجديد نفس الرصيد الحالي، ما في شي يتعدّل.',
                ]);
            }

            $credit = $due = null;

            if ($delta > 0) {
                $credit = ContractorCredit::create([
                    'contractor_id' => $contractor->id,
                    'amount_jod'    => $delta,
                    'used_jod'      => 0,
                    'description'   => self::DESCRIPTION,
                    'source'        => 'manual',
                    'notes'         => $reason,
                    'created_by'    => $by?->id,
                ]);
            } else {
                $due = ContractorDue::create([
                    'contractor_id' => $contractor->id,
                    'year'          => (int) now()->year,
                    'description'   => self::DESCRIPTION,
                    'amount_jod'    => -$delta,
                    'paid_jod'      => 0,
                    'status'        => 'unpaid',
                    'source'        => 'manual',
                    'due_date'      => now()->toDateString(),
                    'notes'         => $reason,
                    'created_by'    => $by?->id,
                ]);
            }

            // نفس سلوك أي ذمة/رصيد جديد: الرصيد الدائن بينصرف على المفتوح حتى ما يضل "له" و"عليه" بنفس الوقت
            $this->credits->applyAvailableCredit($contractor->fresh(), $by);

            [$after, $statusAfter] = $this->current($contractor->id);

            $adjustment = ContractorBalanceAdjustment::create([
                'contractor_id'        => $contractor->id,
                'mode'                 => $mode,
                'amount_jod'           => $delta,
                'balance_before_jod'   => $before,
                'balance_after_jod'    => $after,
                'status_before'        => $statusBefore,
                'status_after'         => $statusAfter,
                'reason'               => $reason,
                'contractor_credit_id' => $credit?->id,
                'contractor_due_id'    => $due?->id,
                'created_by'           => $by?->id,
            ]);

            AuditLogService::recordCritical(
                $by,
                'balance.adjusted',
                $contractor,
                ['net_jod' => $before, 'status' => $this->statusLabel($statusBefore)],
                ['net_jod' => $after, 'status' => $this->statusLabel($statusAfter)],
                $reason,
                [
                    'adjustment_id' => $adjustment->id,
                    'mode'          => ContractorBalanceAdjustment::MODE_LABELS[$mode] ?? $mode,
                    'amount_jod'    => $delta,
                ],
            );

            return $adjustment;
        });
    }

    /** @return array{0: float, 1: string} الرصيد الصافي وحالة العضوية المشتقة منه */
    private function current(int $contractorId): array
    {
        $row = $this->balances->balancesQuery()->where('contractors.id', $contractorId)->first();

        return [round((float) $row->net_jod, 2), $this->balances->membershipStatus($row)];
    }

    private function statusLabel(string $status): string
    {
        return ContractorBalanceController::STATUS_LABELS[$status] ?? $status;
    }
}
