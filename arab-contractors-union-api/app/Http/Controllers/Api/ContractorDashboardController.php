<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class ContractorDashboardController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private \App\Services\ContractorFinancialService $financialService,
        private \App\Services\MembershipStatusService $membershipStatusService
    ) {}
    // ─────────────────────────────────────────────────────────────────────────
    //  GET /api/v1/contractor/dashboard
    // ─────────────────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $contractor = $request->user();

        $activeMembership = $contractor->memberships()
            ->where('status', 'active')
            ->latest('starts_at')
            ->first();

        return $this->success([
            'contractor' => [
                'id'                => $contractor->id,
                'name'              => $contractor->name,
                'membership_number' => $contractor->membership_number,
                'commercial_register' => $contractor->commercial_register,
                'authorized_person' => $contractor->authorized_person,
                'authorized_person_id_number' => $contractor->authorized_person_id_number,
                'authorized_person_phone'     => $contractor->authorized_person_phone,
                'authorized_person_whatsapp'  => $contractor->authorized_person_whatsapp,
                'trade'             => $contractor->trade,
                'classification'    => $contractor->classification,
                'email'             => $contractor->email,
                'phone'             => $contractor->phone,
                'city'              => $contractor->city,
                'address'           => $contractor->address,
                'status'            => $contractor->status,
                'is_frozen'         => $contractor->is_frozen,
            ],
            'membership' => $activeMembership ? [
                'id'          => $activeMembership->id,
                'type'        => $activeMembership->type,
                'status'      => $activeMembership->status,
                'starts_at'   => $activeMembership->starts_at?->toDateString(),
                'expires_at'  => $activeMembership->expires_at?->toDateString(),
                'amount'      => $activeMembership->amount,
                'expiring_soon' => $activeMembership->expiring_soon,
            ] : null,
            'stats' => [
                'total_payments'       => $contractor->payments()->where('status', 'paid')->sum('amount'),
                'pending_payments'     => $contractor->payments()->where('status', 'pending')->count(),
                'total_documents'      => $contractor->documents()->count(),
                'total_equipment'      => $contractor->equipment()->count(),
                'certificate_requests' => $contractor->certificateRequests()->count(),
                'open_tickets'         => \App\Models\SupportTicket::where('contractor_id', $contractor->id)
                                            ->where('status', 'open')->count(),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  GET /api/v1/contractor/memberships
    // ─────────────────────────────────────────────────────────────────────────
    public function memberships(Request $request)
    {
        $memberships = $request->user()
            ->memberships()
            ->latest()
            ->get()
            ->map(fn($m) => [
                'id'          => $m->id,
                'type'        => $m->type,
                'status'      => $m->status,
                'starts_at'   => $m->starts_at?->toDateString(),
                'expires_at'  => $m->expires_at?->toDateString(),
                'amount'      => $m->amount,
                'notes'       => $m->notes,
                'expiring_soon' => $m->expiring_soon,
            ]);

        return $this->success($memberships->toArray());
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  GET /api/v1/contractor/payments
    // ─────────────────────────────────────────────────────────────────────────
    public function payments(Request $request)
    {
        $payments = $request->user()
            ->payments()
            ->with('membership:id,type,expires_at')
            ->latest()
            ->get()
            ->map(fn($p) => [
                'id'               => $p->id,
                'amount'           => $p->amount,
                'type'             => $p->type,
                'status'           => $p->status,
                'method'           => $p->method,
                'reference_number' => $p->reference_number,
                'notes'            => $p->notes,
                'paid_at'          => $p->paid_at?->toDateString(),
                'created_at'       => $p->created_at->toDateString(),
                'membership'       => $p->membership ? [
                    'type'       => $p->membership->type,
                    'expires_at' => $p->membership->expires_at?->toDateString(),
                ] : null,
            ]);

        return $this->success($payments->toArray());
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  GET /api/v1/contractor/financial
    //  الملف المالي: الالتزامات المالية وكشف حساب تفصيلي "ما له وما عليه".
    // ─────────────────────────────────────────────────────────────────────────
    public function financial(Request $request)
    {
        $contractor = $request->user();

        $payments  = $contractor->payments()->with('membership:id,type,expires_at')->get();
        $penalties = $contractor->penalties()->get();
        $dues      = $contractor->dues()->get();

        // ─── كشف الحساب: كل حركة بتاريخها — دائن (مدفوع) / مدين (مستحق) ───
        $statement = collect();

        foreach ($payments as $p) {
            $statement->push([
                'date'        => ($p->paid_at ?? $p->created_at)?->toDateString(),
                'description' => $p->notes
                    ?: ($p->type === 'membership' ? 'رسوم اشتراك عضوية' : ($p->type ?: 'دفعة')),
                'type'        => 'payment',
                'direction'   => $p->status === 'paid' ? 'credit' : 'debit', // له / عليه
                'amount'      => (string) $p->amount,
                'status'      => $p->status,
                'reference'   => $p->reference_number,
            ]);
        }

        foreach ($penalties as $pen) {
            $statement->push([
                'date'        => ($pen->paid_at ?? $pen->created_at)?->toDateString(),
                'description' => $pen->reason ?: 'غرامة',
                'type'        => 'penalty',
                'direction'   => $pen->status === 'paid' ? 'credit' : 'debit',
                'amount'      => (string) $pen->amount,
                'status'      => $pen->status,
                'reference'   => null,
            ]);
        }

        // الذمم المالية السابقة (رسوم سنوات قديمة وغيرها) — دائماً بالدينار الأردني
        foreach ($dues as $due) {
            $statement->push([
                'date'        => ($due->due_date ?? $due->created_at)?->toDateString(),
                'description' => $due->description . ($due->year ? " ({$due->year})" : ''),
                'type'        => 'due',
                'direction'   => $due->status === 'paid' ? 'credit' : 'debit',
                'amount'      => (string) $due->remaining_jod,
                'status'      => $due->status,
                'reference'   => $due->period,
            ]);
        }

        $statement = $statement->sortByDesc('date')->values();

        // ─── الملخص ───
        $totalPaid          = (float) $payments->where('status', 'paid')->sum('amount');
        $pendingPayments    = (float) $payments->where('status', 'pending')->sum('amount');
        $unpaidPenalties    = (float) $penalties->where('status', '!=', 'paid')->sum('amount');
        $outstandingDues    = $this->financialService->outstandingDuesTotal($contractor);
        $totalObligations   = $this->financialService->totalObligations($contractor);
        $duesTotals         = $this->financialService->duesTotalsSummary($contractor);

        // ذمم "قيد المراجعة" (شاشة الذمم المالية) — تحويلات مرفوعة لتسديد ذمم بانتظار تأكيد المحاسبة،
        // مش عمود بجدول contractor_dues، مشتقة من Payment(type=dues_payment, status=pending)
        $pendingDuesPayments = $payments->where('type', 'dues_payment')->where('status', 'pending')->values();

        return $this->success([
            'summary' => [
                'total_paid'           => number_format($totalPaid, 2, '.', ''),        // ما له (مدفوعاته)
                'pending_payments'     => number_format($pendingPayments, 2, '.', ''),
                'unpaid_penalties'     => number_format($unpaidPenalties, 2, '.', ''),
                'outstanding_dues_jod' => number_format($outstandingDues, 2, '.', ''),  // ذمم سابقة بالدينار
                'total_obligations'    => number_format($totalObligations, 2, '.', ''), // ما عليه
                // بطاقة شاشة "الذمم المالية": الرصيد المستحق / المدفوع / إجمالي الرسوم + نسبة السداد
                'dues_total_jod'       => number_format($duesTotals['total'], 2, '.', ''),
                'dues_paid_jod'        => number_format($duesTotals['paid'], 2, '.', ''),
                'dues_paid_percentage' => $this->financialService->duesPaidPercentage($contractor),
            ],
            // لقطة احتساب رسوم السنة الحالية (محرّك الاحتساب الآلي — المادة 37)، إن وُجدت
            'current_year_fee_breakdown' => $dues
                ->first(fn ($d) => $d->source === 'fee_engine' && $d->year === now()->year)
                ?->fee_breakdown,
            // تبويب "المستحقات": بادجات الأعداد (غير مدفوع / قيد المراجعة) لعرضها فوق القائمة
            'dues_counts' => [
                'unpaid'         => $dues->where('status', '!=', 'paid')->count(),
                'pending_review' => $pendingDuesPayments->count(),
            ],
            // تبويب "الدفعات السابقة": بادجات الأعداد (دفع جزئي / مكتمل)
            // دفع جزئي = ذمم partially_paid (تُعرض من مصفوفة dues أدناه بفلتر ?status=partially_paid)
            // مكتمل = تحويلات ذمم مؤكَّدة (تُعرض عبر GET contractor/payments/transfer?status=paid&type=dues_payment)
            'payment_history_counts' => [
                'partially_paid' => $dues->where('status', 'partially_paid')->count(),
                'completed'      => $payments->where('type', 'dues_payment')->where('status', 'paid')->count(),
            ],
            // تحويلات تسديد ذمم بانتظار مراجعة المحاسبة — لكل عنصر reference_number + receipt_image_url
            // جاهزين لزر "معاينة الاشعار المرفوع" (نفس شكل PaymentController::format())
            'pending_dues_payments' => $pendingDuesPayments->map(fn ($p) => [
                'id'                => $p->id,
                'description'       => $p->notes ?: 'دفعة مقدمة للمشروع',
                'amount'            => $p->amount,
                'currency'          => $p->currency ?? 'JOD',
                'reference_number'  => $p->reference_number,
                'receipt_image_url' => $p->receipt_image_url,
                'submitted_at'      => $p->submitted_at,
            ])->values(),
            // فلتر شاشة "الرسوم المالية" بالتطبيق: ?status=unpaid|partially_paid|paid|overdue
            // "متأخرة" محسوبة (غير مسدَّدة بالكامل + تجاوز موعد الاستحقاق) وليست عموداً بقاعدة البيانات.
            'dues' => $this->filterDuesByStatus($dues, $request->string('status')->toString())
                ->map(fn ($d) => [
                    'id'               => $d->id,
                    'year'             => $d->year,
                    'period'           => $d->period,
                    'reference_number' => $d->reference_number,
                    'description'      => $d->description,
                    'amount_jod'    => $d->amount_jod,
                    'paid_jod'      => $d->paid_jod,
                    'remaining_jod' => $d->remaining_jod,
                    'status'        => $d->status,
                    'status_label'  => $d->status_label,
                    'due_date'      => $d->due_date?->toDateString(),
                ])->values(),
            // الالتزامات المستحقة فقط (تُستثنى الدفعات المرفوضة — ليست دينًا قائمًا)
            'obligations' => $statement
                ->where('direction', 'debit')
                ->where('status', '!=', 'rejected')
                ->values(),
            'statement'   => $statement,
        ]);
    }

    /** فلترة قائمة الذمم حسب تبويب شاشة الرسوم المالية (فارغ = بلا فلترة) */
    private function filterDuesByStatus(\Illuminate\Support\Collection $dues, ?string $status): \Illuminate\Support\Collection
    {
        if (empty($status)) {
            return $dues;
        }

        if ($status === 'overdue') {
            return $dues->filter(fn ($d) => $d->status !== 'paid' && $d->due_date && $d->due_date->isPast())->values();
        }

        return $dues->where('status', $status)->values();
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  GET /api/v1/contractor/subscription
    //  كائن موحّد لتفاصيل اشتراك المقاول ووضعه — يُستخدم من أكثر من شاشة بالتطبيق
    //  بدل تكرار حسبة badge/expiring_soon/can_renew في كل مكان.
    // ─────────────────────────────────────────────────────────────────────────
    public function subscription(Request $request)
    {
        return $this->success($this->membershipStatusService->getSubscriptionStatus($request->user()));
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  GET /api/v1/contractor/renewal-eligibility
    //  هل يستطيع المقاول تجديد عضويته؟ (تُمنع مع ذمم/غرامات غير مسدَّدة)
    // ─────────────────────────────────────────────────────────────────────────
    public function renewalEligibility(Request $request)
    {
        $contractor = $request->user();
        $blockers   = \App\Support\ContractorRequirements::renewalBlockers($contractor);

        return $this->success([
            'can_renew'             => count($blockers) === 0,
            'issues'                => $blockers,
            'outstanding_total_jod' => $this->financialService->outstandingDuesTotal($contractor),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  GET /api/v1/contractor/documents
    // ─────────────────────────────────────────────────────────────────────────
    public function documents(Request $request)
    {
        $documents = $request->user()
            ->documents()
            ->latest()
            ->get()
            ->map(fn($d) => [
                'id'             => $d->id,
                'title'          => $d->title,
                'type'           => $d->type,
                'url'            => $d->url,
                'mime_type'      => $d->mime_type,
                'formatted_size' => $d->formatted_size,
                'created_at'     => $d->created_at->toDateString(),
            ]);

        return $this->success($documents->values());
    }
}
