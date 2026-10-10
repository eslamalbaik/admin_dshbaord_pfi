<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * تصنيف أحداث سجل النشاط المالية — بتظهر بتبويب «سجل النشاط المالي» بصفحة سجل النشاط.
 *
 * أي نوع إجراء جديد بيبدأ بواحدة من البادئات (payment. / due. / penalty. ...) بينضاف
 * للتبويب تلقائياً. الأنواع المالية اللي ما إلها بادئة مالية بتنضاف لـ ACTIONS.
 */
class FinancialEvents
{
    /** دفعات، ذمم وخصومات وتسويات، غرامات، تعديل الرصيد اليدوي، رصيد دائن، سعر الصرف */
    public const PREFIXES = [
        'payment.',
        'due.',
        'penalty.',
        'balance.',
        'credit.',
        'exchange_rate.',
    ];

    /** أحداث مالية بأسماء خارج البادئات: رسوم الدرجات، وعكس تجديد عضوية بعد إلغاء دفعتها */
    public const ACTIONS = [
        'grade_fee.updated',
        'membership.renewal_reversed',
    ];

    public static function isFinancial(string $action): bool
    {
        if (in_array($action, self::ACTIONS, true)) {
            return true;
        }

        foreach (self::PREFIXES as $prefix) {
            if (str_starts_with($action, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /** تقييد استعلام سجل النشاط على الأحداث المالية فقط */
    public static function scope(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            foreach (self::PREFIXES as $prefix) {
                $q->orWhere('action', 'like', $prefix . '%');
            }
            $q->orWhereIn('action', self::ACTIONS);
        });
    }
}
