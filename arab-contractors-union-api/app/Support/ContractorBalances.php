<?php

namespace App\Support;

use App\Models\Contractor;
use Illuminate\Database\Query\Builder;

/**
 * حسبة صافي وضع كل شركة المالي مع الاتحاد بالدينار — مصدر واحد تستهلكه شاشة الأرصدة
 * وتطبيق المقاول (ContractorBalanceController) وجبر الكسور (BalanceRoundingService).
 *
 * له    = الأرصدة الدائنة (contractor_credits) غير المستخدمة + فائض دفعات الذمم والغرامات
 *         والدفعات المقدمة (Payment::CREDIT_TYPES) الذي لم يُوزَّع على ذمم.
 * عليه  = المتبقي من الذمم غير المسدَّدة + المتبقي من الغرامات غير المسدَّدة (بالدينار).
 * الصافي = له − عليه (سالب = الشركة مطلوب منها للاتحاد).
 */
class ContractorBalances
{
    /** فائض دفعات الذمم/الغرامات والدفعات المقدمة غير الموزَّع يُعتبر رصيداً للشركة */
    public const CREDIT_PAYMENT_TYPES = \App\Models\Payment::CREDIT_TYPES;

    public static function query(): Builder
    {
        $types = "'" . implode("','", self::CREDIT_PAYMENT_TYPES) . "'";

        $credit = "((SELECT COALESCE(SUM(cr.amount_jod - cr.used_jod), 0) FROM contractor_credits cr
                     WHERE cr.contractor_id = contractors.id AND cr.deleted_at IS NULL)
                  + (SELECT COALESCE(SUM(p.amount_jod - COALESCE(p.used_amount_jod, 0)), 0) FROM payments p
                     WHERE p.contractor_id = contractors.id AND p.status = 'paid' AND p.type IN ({$types})))";
        $dues = "(SELECT COALESCE(SUM(d.amount_jod - d.paid_jod), 0) FROM contractor_dues d
                  WHERE d.contractor_id = contractors.id AND d.status <> 'paid' AND d.deleted_at IS NULL)";
        $penalties = "(SELECT COALESCE(SUM(pe.amount - COALESCE(pe.paid_amount, 0)), 0) FROM penalties pe
                       WHERE pe.contractor_id = contractors.id AND pe.status IN ('unpaid', 'partially_paid'))";
        $exact = "ROUND({$credit} - {$dues} - {$penalties}, 2)";

        // مجموع كل الذمم والغرامات (المسدَّد وغير المسدَّد) — نفس عمود "الإجمالي" بصفحة الذمم
        $obligations = "((SELECT COALESCE(SUM(d.amount_jod), 0) FROM contractor_dues d
                          WHERE d.contractor_id = contractors.id AND d.deleted_at IS NULL)
                       + (SELECT COALESCE(SUM(pe.amount), 0) FROM penalties pe
                          WHERE pe.contractor_id = contractors.id AND pe.status <> 'rejected'))";

        // floor بدون FLOOR(): غير موجودة بـSQLite الاختبارات. الصافي الدقيق بخانتين عشريتين
        // بالضبط، فطرح 0.495 ثم التقريب لعدد صحيح يساوي floor تماماً (300.00 ← 300، 300.99 ← 300، 349.01- ← 350-).
        // بعد BalanceRoundingService الصافي المخزَّن أصلاً عدد صحيح، فهذا مجرد ضمان للعرض.
        return Contractor::query()->toBase()->select([
            'contractors.id', 'contractors.name', 'contractors.membership_number', 'contractors.status',
        ])->selectRaw("ROUND({$credit}, 2) AS credit_jod")
            ->selectRaw("ROUND({$dues}, 2) AS dues_jod")
            ->selectRaw("ROUND({$penalties}, 2) AS penalties_jod")
            ->selectRaw("{$exact} AS net_exact_jod")
            ->selectRaw("ROUND({$exact} - 0.495, 0) AS net_jod")
            ->selectRaw("ROUND({$obligations}, 2) AS obligations_jod")
            ->selectRaw('EXISTS(SELECT 1 FROM contractor_dues cy WHERE cy.contractor_id = contractors.id
                          AND cy.deleted_at IS NULL AND cy.year = ?) AS has_current_year_due', [now()->year]);
    }

    /** الصافي الدقيق لشركة واحدة (قبل الجبر) */
    public static function exactNet(int $contractorId): float
    {
        $row = self::query()->where('contractors.id', $contractorId)->first();

        return $row ? round((float) $row->net_exact_jod, 2) : 0.0;
    }
}
