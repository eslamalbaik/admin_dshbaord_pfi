<?php

namespace App\Support;

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

        // غرامات تأخير غير مدفوعة
        foreach ($contractor->penalties()->where('status', 'unpaid')->get() as $penalty) {
            $issues[] = [
                'type'        => 'late_fees',
                'description' => $penalty->reason ?: 'غرامة تأخير غير مدفوعة',
                'amount'      => $penalty->amount,
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

        // لا توجد عضوية نشطة
        if (! $contractor->activeMembership) {
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
     * الذمم والغرامات فقط — غياب العضوية النشطة ليس مانعاً للتجديد بل سببه.
     */
    public static function renewalBlockers(Contractor $contractor): array
    {
        return array_values(array_filter(
            self::issues($contractor),
            fn ($issue) => in_array($issue['type'], ['unpaid_dues', 'late_fees', 'pending_dispute'], true),
        ));
    }

    /** مفتاح تفعيل منع التجديد بالذمم — يبدأ مطفأً حتى اكتمال الاستيراد ومراجعته */
    public static function duesBlockingEnabled(): bool
    {
        return (bool) Setting::get('enforce_dues_blocking', '0');
    }
}
