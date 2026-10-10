<?php

namespace App\Support;

use App\Http\Controllers\Api\ContractorBalanceController;
use App\Models\Contractor;
use App\Models\Setting;

/**
 * المتطلبات المالية/الإدارية التي تمنع المقاول من طلب الشهادات أو تجديد العضوية.
 * تُستخدم في طلبات الشهادات وفي بوابة الدفع (تجديد العضوية).
 */
class ContractorRequirements
{
    /**
     * @return array<int, array{type: string, description: string, amount: mixed, due_date: mixed}>
     */
    public static function issues(Contractor $contractor): array
    {
        $issues = [];

        // غرامات تأخير غير مدفوعة أو مسدَّدة جزئياً فقط — المرفوضة والمسدَّدة بالكامل لا تُحتسَب
        foreach ($contractor->penalties()->whereIn('status', ['unpaid', 'partially_paid'])->get() as $penalty) {
            $issues[] = [
                'type'        => 'late_fees',
                'description' => $penalty->reason ?: 'غرامة تأخير غير مدفوعة',
                'amount'      => $penalty->amount - $penalty->paid_amount,
                'due_date'    => null,
            ];
        }

        // ذمم مالية سابقة غير مسدَّدة (تُفعَّل بعد اكتمال استيراد البيانات القديمة)
        if (self::duesBlockingEnabled()) {
            foreach ($contractor->dues()->outstanding()->get() as $due) {
                $issues[] = [
                    'type'        => 'unpaid_dues',
                    'description' => $due->description . ($due->year ? " ({$due->year})" : ''),
                    'amount'      => $due->remaining_jod,
                    'due_date'    => $due->due_date?->toDateString(),
                ];
            }
        }

        // حساب مجمّد
        if ($contractor->is_frozen) {
            $issues[] = [
                'type'        => 'pending_dispute',
                'description' => 'الحساب مجمّد حالياً — يُرجى مراجعة إدارة الاتحاد.',
                'amount'      => null,
                'due_date'    => null,
            ];
        }

        // العضوية غير سارية — حسب نفس الحالة المعروضة للمقاول بالتطبيق وصفحة الأرصدة
        // (مشتقّة من الرصيد وقاعدة الـ95%)، مش حسب وجود سجل memberships بحالة active:
        // كان المقاول اللي حالته "فعّالة" وما عليه شي ينمنع من الشهادة لأنه ما انعملّه
        // سجل تجديد (2026-10-10).
        if (! self::hasActiveMembership($contractor)) {
            $issues[] = [
                'type'        => 'overdue_subscription',
                'description' => 'لا يوجد اشتراك عضوية نشط — يُرجى تجديد العضوية.',
                'amount'      => null,
                'due_date'    => null,
            ];
        }

        return $issues;
    }

    /**
     * المتطلبات المانعة لتجديد العضوية تحديداً:
     * الذمم والغرامات، بالإضافة لحساب موقوف إدارياً (status=suspended) — هذا
     * الأخير يمنع التجديد فقط، لا الدخول للتطبيق (راجع is_frozen لقفل الحساب
     * كاملاً في EnsureContractorIsActive). غياب العضوية النشطة ليس مانعاً
     * للتجديد بل سببه.
     */
    public static function renewalBlockers(Contractor $contractor): array
    {
        $blockers = array_values(array_filter(
            self::issues($contractor),
            fn ($issue) => in_array($issue['type'], ['unpaid_dues', 'late_fees', 'pending_dispute'], true),
        ));

        if ($contractor->status === 'suspended') {
            $blockers[] = [
                'type'        => 'account_suspended',
                'description' => 'حسابك موقوف إدارياً — يُرجى مراجعة إدارة الاتحاد قبل تجديد العضوية.',
                'amount'      => null,
                'due_date'    => null,
            ];
        }

        return $blockers;
    }

    /**
     * هل للمقاول عضوية سارية: سجل عضوية نشط، أو حالته المعروضة "فعّالة"
     * (ContractorBalanceController::membershipStatus — نفس صفحة الأرصدة والتطبيق).
     * اللي عنده سجل نشط بس عليه ذمم بيضل يعدّي هون، وبيوقفه فحص الـ95% برسالة المبلغ
     * المتبقي (ومسار الدفعة المعلّقة) بدل "لا يوجد اشتراك".
     */
    public static function hasActiveMembership(Contractor $contractor): bool
    {
        return $contractor->activeMembership !== null || self::displayedStatusIsActive($contractor);
    }

    public static function displayedStatusIsActive(Contractor $contractor): bool
    {
        return app(ContractorBalanceController::class)->statusFor($contractor->id) === 'active';
    }

    /** مفتاح تفعيل منع التجديد بالذمم — يبدأ مطفأً حتى اكتمال الاستيراد ومراجعته */
    public static function duesBlockingEnabled(): bool
    {
        return (bool) Setting::get('enforce_dues_blocking', '0');
    }
}
