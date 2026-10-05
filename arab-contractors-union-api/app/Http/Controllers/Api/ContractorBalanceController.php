<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Contractor;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * أرصدة المقاولين: صافي وضع كل شركة المالي مع الاتحاد بالدينار الأردني.
 *
 * له    = الأرصدة الدائنة (contractor_credits) غير المستخدمة + فائض دفعات الذمم
 *         (dues_payment) الذي لم يُوزَّع على ذمم.
 * عليه  = المتبقي من الذمم غير المسدَّدة + المتبقي من الغرامات غير المسدَّدة (بالدينار).
 * الصافي = له − عليه (سالب = الشركة مطلوب منها للاتحاد).
 *
 * يُحسب لحظياً، فأي ذمة أو غرامة أو رسوم جديدة أو دفعة تنعكس مباشرة.
 */
class ContractorBalanceController extends Controller
{
    use ApiResponseTrait;

    /** فائض دفعات الذمم غير الموزَّع يُعتبر رصيداً للشركة */
    public const CREDIT_PAYMENT_TYPES = ['dues_payment'];

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
        $row = $this->balancesQuery()->where('contractors.id', $request->user()->id)->first();

        $balance = $this->formatRow($row);
        unset($balance['status']);

        // له / عليه / متوازن — لتلوين البطاقة بالتطبيق بدون ما يقارن الرقم بصفر بنفسه
        $balance['position'] = match (true) {
            $balance['net_jod'] > 0 => 'credit',
            $balance['net_jod'] < 0 => 'owes',
            default                 => 'settled',
        };
        $balance['currency'] = 'JOD';

        return $this->success($balance);
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
     * حالة العضوية الفعلية لحساب "فعّال" إدارياً:
     * - آخر عضوية إله انتهت (وما جدّد) ← "منتهية".
     * - عليه رصيد صافي سالب (ذمم/غرامات ما بيغطيها رصيده) ← "منتهية" كمان، لأن مقاول عليه
     *   رسوم ما بيصير ينعرض "فعّالة" (طلب الإدارة 2026-10-05).
     * باقي الحالات الإدارية (معلّق، موقوف، منتهي) بتنعرض كما هي.
     */
    private function membershipStatus(object $row): string
    {
        if ($row->status !== 'active') {
            return $row->status;
        }

        $ended = $row->membership_expires_at
            && now()->startOfDay()->gt(Carbon::parse($row->membership_expires_at));

        // عضوية انتهت، أو عليه رسوم ما بيغطيها رصيده (صافي سالب): كلاهما "منتهية"
        if ($ended || (float) $row->net_jod < 0) {
            return 'expired';
        }

        return 'active';
    }

    private function balancesQuery(): Builder
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

        return Contractor::query()->toBase()->select([
            'contractors.id', 'contractors.name', 'contractors.membership_number', 'contractors.status',
        ])->selectRaw("ROUND({$credit}, 2) AS credit_jod")
            ->selectRaw("ROUND({$dues}, 2) AS dues_jod")
            ->selectRaw("ROUND({$penalties}, 2) AS penalties_jod")
            ->selectRaw("ROUND({$credit} - {$dues} - {$penalties}, 2) AS net_jod")
            ->selectRaw("(SELECT MAX(m.expires_at) FROM memberships m
                          WHERE m.contractor_id = contractors.id AND m.status IN ('active', 'expired')) AS membership_expires_at");
    }
}
