<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class ContractorDashboardController extends Controller
{
    use ApiResponseTrait;
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
                'total_payments'   => $contractor->payments()->where('status', 'paid')->sum('amount'),
                'pending_payments' => $contractor->payments()->where('status', 'pending')->count(),
                'total_documents'  => $contractor->documents()->count(),
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
        $outstandingDues    = $contractor->outstandingDuesTotal();
        $totalObligations   = $pendingPayments + $unpaidPenalties + $outstandingDues;

        return $this->success([
            'summary' => [
                'total_paid'           => number_format($totalPaid, 2, '.', ''),        // ما له (مدفوعاته)
                'pending_payments'     => number_format($pendingPayments, 2, '.', ''),
                'unpaid_penalties'     => number_format($unpaidPenalties, 2, '.', ''),
                'outstanding_dues_jod' => number_format($outstandingDues, 2, '.', ''),  // ذمم سابقة بالدينار
                'total_obligations'    => number_format($totalObligations, 2, '.', ''), // ما عليه
            ],
            'dues' => $dues->map(fn ($d) => [
                'id'            => $d->id,
                'year'          => $d->year,
                'period'        => $d->period,
                'description'   => $d->description,
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
            'outstanding_total_jod' => $contractor->outstandingDuesTotal(),
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

        return response()->json(['data' => $documents]);
    }
}
