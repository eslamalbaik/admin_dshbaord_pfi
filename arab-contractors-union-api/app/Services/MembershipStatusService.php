<?php

namespace App\Services;

use App\Models\Contractor;
use App\Support\ContractorRequirements;

class MembershipStatusService
{
    public function __construct(private ContractorFinancialService $financialService)
    {}

    /**
     * كائن موحّد لحالة اشتراك المقاول — نداء واحد يجمع تفاصيل العضوية النشطة،
     * الـ badge (active/expired/status الإداري)، أهلية التجديد وموانعه.
     * مصدر واحد يُستخدم من أكثر من نقطة نهاية بدل تكرار نفس الحسبة.
     */
    public function getSubscriptionStatus(Contractor $contractor): array
    {
        // العضوية الفعّالة صاحبة أبعد تاريخ انتهاء — نفس التاريخ اللي بتنحسب منه الحالة بالداشبورد
        $membership = $contractor->memberships()->where('status', 'active')
            ->orderByDesc('expires_at')->orderByDesc('id')->first();

        // الحالة من تاريخ انتهاء العضوية بس، بنفس حسبة الداشبورد وصفحة الأرصدة وبوابة الشهادة
        $badge = app(\App\Http\Controllers\Api\ContractorBalanceController::class)->statusFor($contractor->id);

        $blockers = ContractorRequirements::renewalBlockers($contractor);

        return [
            'id'                    => $membership?->id,
            'type'                  => $membership?->type,
            'membership_status'     => $membership?->status,
            'badge'                 => $badge,
            'starts_at'             => $membership?->starts_at?->toDateString(),
            'expires_at'            => $membership?->expires_at?->toDateString(),
            'amount'                => $membership?->amount,
            'expiring_soon'         => (bool) $membership?->expiring_soon,
            'days_remaining'        => $membership?->expires_at ? max(0, (int) now()->diffInDays($membership->expires_at, false)) : null,
            'can_renew'             => count($blockers) === 0,
            'renewal_blockers'      => $blockers,
            'outstanding_total_jod' => $this->financialService->outstandingDuesTotal($contractor),
        ];
    }
}
