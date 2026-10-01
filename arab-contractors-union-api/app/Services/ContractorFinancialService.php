<?php

namespace App\Services;

use App\Models\Contractor;

class ContractorFinancialService
{
    /** الحد الأدنى لنسبة سداد الذمم لإصدار شهادة العضوية (REQ-02) */
    public const MEMBERSHIP_CERT_MIN_PAID_PERCENT = 95.0;

    /** سقف هامش السماح المطلق بالدينار — أيهما أقل يُعتمَد: 5% من الإجمالي أو 50 دينار */
    public const MEMBERSHIP_CERT_MAX_MARGIN_JOD = 50.0;

    /** إجمالي الذمم المتبقية بالدينار الأردني */
    public function outstandingDuesTotal(Contractor $contractor): float
    {
        return round((float) $contractor->dues()
            ->outstanding()
            ->selectRaw('COALESCE(SUM(amount_jod - paid_jod), 0) as total')
            ->value('total'), 2);
    }

    /** إجمالي "ما عليه" — دفعات معلّقة + غرامات غير مسدَّدة (بالباقي منها) + ذمم سابقة */
    public function totalObligations(Contractor $contractor): float
    {
        $pendingPayments = (float) $contractor->payments()->where('status', 'pending')->sum('amount');

        // paid/rejected لا تُحتسَب، وpartially_paid تُحتسَب بالمتبقي منها فقط لا بكامل مبلغها
        // الأصلي — نفس منطق ContractorRequirements::issues() تماماً (REQ-06 #6).
        $unpaidPenalties = (float) $contractor->penalties()
            ->whereIn('status', ['unpaid', 'partially_paid'])
            ->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as total')
            ->value('total');

        return round($pendingPayments + $unpaidPenalties + $this->outstandingDuesTotal($contractor), 2);
    }

    /** نسبة ما سُدِّد من إجمالي الذمم (amount_jod مقابل paid_jod) — 100% إن لم توجد ذمم مسجّلة */
    public function duesPaidPercentage(Contractor $contractor): float
    {
        $totals = $this->duesTotals($contractor);
        $total  = $totals['total'];

        if ($total <= 0) {
            return 100.0;
        }

        return round(($totals['paid'] / $total) * 100, 2);
    }

    /** @return array{total: float, paid: float, outstanding: float} */
    public function duesTotalsSummary(Contractor $contractor): array
    {
        return $this->duesTotals($contractor);
    }

    /** @return array{total: float, paid: float, outstanding: float} */
    private function duesTotals(Contractor $contractor): array
    {
        $totals = $contractor->dues()
            ->selectRaw('COALESCE(SUM(amount_jod), 0) as total, COALESCE(SUM(paid_jod), 0) as paid')
            ->first();

        $total = (float) $totals->total;
        $paid  = (float) $totals->paid;

        return ['total' => $total, 'paid' => $paid, 'outstanding' => round($total - $paid, 2)];
    }

    /** @return array{total: float, paid: float, outstanding: float} ذمم السنة الحالية فقط */
    public function currentYearDuesTotals(Contractor $contractor): array
    {
        $totals = $contractor->dues()
            ->where('year', now()->year)
            ->selectRaw('COALESCE(SUM(amount_jod), 0) as total, COALESCE(SUM(paid_jod), 0) as paid')
            ->first();

        $total = (float) $totals->total;
        $paid  = (float) $totals->paid;

        return ['total' => $total, 'paid' => $paid, 'outstanding' => round($total - $paid, 2)];
    }

    /** نسبة سداد ذمم السنة الحالية — 100% إن لم توجد ذمم مسجّلة لهذه السنة بعد */
    public function currentYearDuesPaidPercentage(Contractor $contractor): float
    {
        $totals = $this->currentYearDuesTotals($contractor);

        if ($totals['total'] <= 0) {
            return 100.0;
        }

        return round(($totals['paid'] / $totals['total']) * 100, 2);
    }

    /** المبلغ بالدينار اللازم سداده لبلوغ نسبة 95% من ذمم السنة الحالية — 0 إن لم توجد ذمم لهذه السنة */
    public function remainingToReach95PercentJod(Contractor $contractor): float
    {
        $totals = $this->currentYearDuesTotals($contractor);

        if ($totals['total'] <= 0) {
            return 0.0;
        }

        return max(0.0, round($totals['total'] * self::MEMBERSHIP_CERT_MIN_PAID_PERCENT / 100 - $totals['paid'], 2));
    }

    /**
     * هامش السماح المسموح بالدينار لإصدار شهادة العضوية — أيهما أقل: 5% من إجمالي
     * الذمم أو سقف {@see MEMBERSHIP_CERT_MAX_MARGIN_JOD}
     */
    public function membershipCertAllowedMarginJod(Contractor $contractor): float
    {
        $total = $this->duesTotals($contractor)['total'];

        return min($total * (100 - self::MEMBERSHIP_CERT_MIN_PAID_PERCENT) / 100, self::MEMBERSHIP_CERT_MAX_MARGIN_JOD);
    }

    /** هل نسبة سداد ذمم السنة الحالية تبلغ 95% فأكثر؟ */
    public function isEligibleForMembershipCertificate(Contractor $contractor): bool
    {
        return $this->currentYearDuesPaidPercentage($contractor) >= self::MEMBERSHIP_CERT_MIN_PAID_PERCENT;
    }
}
