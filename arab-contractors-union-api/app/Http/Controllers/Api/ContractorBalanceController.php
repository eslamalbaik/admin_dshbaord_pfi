<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Contractor;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * أرصدة المقاولين: صافي وضع كل شركة المالي مع الاتحاد بالدينار الأردني.
 *
 * له    = الأرصدة الدائنة (contractor_credits) غير المستخدمة + فائض دفعات الذمم والغرامات
 *         والدفعات المقدمة (Payment::CREDIT_TYPES) الذي لم يُوزَّع على ذمم.
 * عليه  = المتبقي من الذمم غير المسدَّدة + المتبقي من الغرامات غير المسدَّدة (بالدينار).
 * الصافي = له − عليه (سالب = الشركة مطلوب منها للاتحاد).
 *
 * يُحسب لحظياً، فأي ذمة أو غرامة أو رسوم جديدة أو دفعة تنعكس مباشرة.
 */
class ContractorBalanceController extends Controller
{
    use ApiResponseTrait;

    /** فائض دفعات الذمم/الغرامات والدفعات المقدمة غير الموزَّع يُعتبر رصيداً للشركة */
    public const CREDIT_PAYMENT_TYPES = \App\Models\Payment::CREDIT_TYPES;

    /**
     * نسبة السداد اللي بتكفي لتنعرض العضوية "فعّالة" وهو لسا عليه باقي بسيط (قرار eslam 2026-10-10):
     * سدّد 95% أو أكثر من مجموع ذممه وغراماته ← فعّالة، والباقي بيضل ذمة عليه.
     */
    public const ACTIVE_PAID_RATIO = 0.95;

    /** نفس تسميات عمود "حالة العضوية" بصفحة الأرصدة */
    public const STATUS_LABELS = [
        'active'    => 'فعّالة',
        'expired'   => 'منتهية',
        'pending'   => 'قيد المراجعة',
        'suspended' => 'موقوفة',
    ];

    /** GET /api/v1/dashboard/balances */
    public function index(Request $request)
    {
        $query = DB::query()->fromSub($this->balancesQuery(), 'b');

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(fn ($qb) => $qb
                ->where('name', 'like', "%{$q}%")
                ->orWhere('membership_number', 'like', "%{$q}%"));
        }

        match ($request->input('filter')) {
            'owes'   => $query->where('net_jod', '<', 0),
            'credit' => $query->where('net_jod', '>', 0),
            'zero'   => $query->where('net_jod', 0),
            default  => null,
        };

        $direction = $request->input('sort') === 'desc' ? 'desc' : 'asc';

        $paginator = $query->orderBy('net_jod', $direction)->orderBy('id')
            ->paginate(min($request->integer('per_page', 15), 500))
            ->through(fn ($row) => $this->formatRow($row));

        return $this->paginated($paginator);
    }

    /**
     * GET /api/v1/contractor/balance
     * رصيد المقاول نفسه بتطبيق المقاول — نفس حسبة شاشة الأرصدة بالداشبورد حرفياً
     * حتى ما يطلع رقم مختلف بين ما يشوفه المقاول وما يشوفه المحاسب.
     */
    public function mine(Request $request)
    {
        return $this->success($this->snapshot($request->user()->id));
    }

    /**
     * رصيد مقاول واحد بشكل جاهز للتطبيق (contractor/balance والشاشة الرئيسية):
     * الصافي بإشارته + المتبقي المطلوب بعد خصم الرصيد + حالة الاشتراك.
     */
    public function snapshot(int $contractorId): array
    {
        $row = $this->balancesQuery()->where('contractors.id', $contractorId)->first();

        $balance = $this->formatRow($row);
        unset($balance['status']);

        // المطلوب دفعه فعلياً بعد خصم الأرصدة والدفعات السابقة (صفر لو ما عليه شي)
        $balance['amount_due_jod'] = max(0.0, round(-$balance['net_jod'], 2));

        // حالة الاشتراك بنفس قاعدة الداشبورد (عليه ذمم = منتهية)، حتى يعرض التطبيق نفس اللي بيشوفه المحاسب
        $balance['subscription_status'] = $this->membershipStatus($row);
        $balance['subscription_status_label'] = self::STATUS_LABELS[$balance['subscription_status']]
            ?? $balance['subscription_status'];

        // له / عليه / متوازن — لتلوين البطاقة بالتطبيق بدون ما يقارن الرقم بصفر بنفسه
        $balance['position'] = match (true) {
            $balance['net_jod'] > 0 => 'credit',
            $balance['net_jod'] < 0 => 'owes',
            default                 => 'settled',
        };
        $balance['currency'] = 'JOD';

        return $balance;
    }

    /** GET /api/v1/contractor/payments/statement — سجل مدفوعات المقاول التفصيلي بالتطبيق */
    public function myStatement(Request $request, \App\Services\ContractorStatementService $statements)
    {
        return $this->success($statements->build($request->user()));
    }

    /** GET /api/v1/dashboard/balances/{contractor}/statement — نفس السجل للمحاسب من صفحة الأرصدة */
    public function statement(Contractor $contractor, \App\Services\ContractorStatementService $statements)
    {
        return $this->success($statements->build($contractor));
    }

    /**
     * POST /api/v1/dashboard/balances/{contractor}/adjust
     * تعديل رصيد المقاول يدوياً (زيادة/إنقاص/تحديد رصيد جديد) مع سبب إلزامي.
     */
    public function adjust(Request $request, Contractor $contractor, \App\Services\ContractorBalanceAdjustmentService $service)
    {
        $data = $request->validate([
            'mode'   => 'required|in:increase,decrease,set',
            'amount' => $request->input('mode') === 'set'
                ? 'required|numeric|between:-10000000,10000000'
                : 'required|numeric|min:0.01|max:10000000',
            'reason' => 'required|string|min:3|max:500',
        ], [
            'mode.required'   => 'اختر نوع التعديل.',
            'amount.required' => 'أدخل المبلغ.',
            'amount.min'      => 'المبلغ لازم يكون أكبر من صفر.',
            'reason.required' => 'سبب التعديل مطلوب.',
            'reason.min'      => 'اكتب سبباً واضحاً للتعديل.',
        ]);

        $adjustment = $service->adjust($contractor, $data['mode'], (float) $data['amount'], trim($data['reason']), $request->user());

        return $this->success([
            'adjustment' => $this->formatAdjustment($adjustment->load('createdBy:id,name')),
            'balance'    => $this->snapshot($contractor->id),
        ], 'تم تعديل الرصيد بنجاح');
    }

    /** GET /api/v1/dashboard/balances/{contractor}/adjustments — سجل تعديلات الرصيد اليدوية */
    public function adjustments(Contractor $contractor)
    {
        $items = \App\Models\ContractorBalanceAdjustment::with('createdBy:id,name')
            ->where('contractor_id', $contractor->id)
            ->latest('id')->limit(100)->get()
            ->map(fn ($a) => $this->formatAdjustment($a));

        return $this->success($items);
    }

    private function formatAdjustment(\App\Models\ContractorBalanceAdjustment $a): array
    {
        return [
            'id'                 => $a->id,
            'mode'               => $a->mode,
            'mode_label'         => \App\Models\ContractorBalanceAdjustment::MODE_LABELS[$a->mode] ?? $a->mode,
            'amount_jod'         => (float) $a->amount_jod,
            'balance_before_jod' => (float) $a->balance_before_jod,
            'balance_after_jod'  => (float) $a->balance_after_jod,
            'status_before'      => $a->status_before,
            'status_after'       => $a->status_after,
            'reason'             => $a->reason,
            'created_by'         => $a->createdBy?->name,
            'created_at'         => $a->created_at?->toIso8601String(),
        ];
    }

    /** GET /api/v1/dashboard/balances/summary */
    public function summary()
    {
        $totals = DB::query()->fromSub($this->balancesQuery(), 'b')->selectRaw('
            COALESCE(SUM(CASE WHEN net_jod > 0 THEN net_jod END), 0)  AS credit_total,
            COALESCE(SUM(CASE WHEN net_jod < 0 THEN net_jod END), 0)  AS debit_total,
            COALESCE(SUM(net_jod), 0)                                 AS net_total,
            SUM(net_jod > 0) AS credit_count,
            SUM(net_jod < 0) AS debit_count,
            SUM(net_jod = 0) AS zero_count
        ')->first();

        return $this->success([
            'credit_total_jod' => round((float) $totals->credit_total, 2),
            'debit_total_jod'  => round((float) $totals->debit_total, 2),
            'net_total_jod'    => round((float) $totals->net_total, 2),
            'credit_count'     => (int) $totals->credit_count,
            'debit_count'      => (int) $totals->debit_count,
            'zero_count'       => (int) $totals->zero_count,
        ]);
    }

    private function formatRow(object $row): array
    {
        return [
            'contractor_id'     => $row->id,
            'name'              => $row->name,
            'membership_number' => $row->membership_number,
            'status'            => $this->membershipStatus($row),
            'credit_jod'        => round((float) $row->credit_jod, 2),
            'dues_jod'          => round((float) $row->dues_jod, 2),
            'penalties_jod'     => round((float) $row->penalties_jod, 2),
            'debit_jod'         => round((float) $row->dues_jod + (float) $row->penalties_jod, 2),
            'net_jod'           => round((float) $row->net_jod, 2),
        ];
    }

    /**
     * حالة العضوية الفعلية لحساب "فعّال" إدارياً، حسب الرصيد بس (قرار الإدارة 2026-10-05):
     * - عليه رصيد صافي سالب أكبر من 5% من مجموع ذممه وغراماته ← "منتهية".
     * - رصيده صفر أو له، أو اللي عليه ≤ 5% (يعني سدّد 95% فأكثر، قرار 2026-10-10) ← "فعّالة"،
     *   حتى لو تاريخ آخر عضوية مسجّلة فات، لأن الرسوم السنوية بتنزل كذمم.
     * باقي الحالات الإدارية (معلّق، موقوف، منتهي) بتنعرض كما هي.
     */
    public function membershipStatus(object $row): string
    {
        // "منتهية" إدارياً كمان بتمشي على قاعدة الـ95% إذا انحسبت عليه رسوم السنة الحالية:
        // المقاول اللي سدّد 95% من كل اللي عليه (ومنها رسوم هالسنة) عضويته سارية، حتى لو ما
        // انعملّه تجديد يدوي (حالة 9565_g: 608 من 640). بدون ذمة للسنة الحالية بتضل "منتهية"،
        // عشان المنتهي القديم اللي ما عليه شي ما ينقلب "فعّالة" لحاله.
        $eligible = $row->status === 'active'
            || ($row->status === 'expired' && ! empty($row->has_current_year_due));

        if (! $eligible) {
            return $row->status;
        }

        $owed = round(-(float) $row->net_jod, 2);
        if ($owed <= 0) {
            return 'active';
        }

        $allowed = round((float) ($row->obligations_jod ?? 0) * (1 - self::ACTIVE_PAID_RATIO), 2);

        // مقارنة بالقروش (أعداد صحيحة) عشان حالة الـ95% بالضبط ما تفشل بفرق كسور عشرية
        return (int) round($owed * 100) <= (int) round($allowed * 100) ? 'active' : 'expired';
    }

    public function balancesQuery(): Builder
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

        // مجموع كل الذمم والغرامات (المسدَّد وغير المسدَّد) — نفس عمود "الإجمالي" بصفحة الذمم
        $obligations = "((SELECT COALESCE(SUM(d.amount_jod), 0) FROM contractor_dues d
                          WHERE d.contractor_id = contractors.id AND d.deleted_at IS NULL)
                       + (SELECT COALESCE(SUM(pe.amount), 0) FROM penalties pe
                          WHERE pe.contractor_id = contractors.id AND pe.status <> 'rejected'))";

        return Contractor::query()->toBase()->select([
            'contractors.id', 'contractors.name', 'contractors.membership_number', 'contractors.status',
        ])->selectRaw("ROUND({$credit}, 2) AS credit_jod")
            ->selectRaw("ROUND({$dues}, 2) AS dues_jod")
            ->selectRaw("ROUND({$penalties}, 2) AS penalties_jod")
            ->selectRaw("ROUND({$credit} - {$dues} - {$penalties}, 2) AS net_jod")
            ->selectRaw("ROUND({$obligations}, 2) AS obligations_jod")
            ->selectRaw('EXISTS(SELECT 1 FROM contractor_dues cy WHERE cy.contractor_id = contractors.id
                          AND cy.deleted_at IS NULL AND cy.year = ?) AS has_current_year_due', [now()->year]);
    }
}
