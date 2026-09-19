<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Penalty;
use Illuminate\Http\Request;

class PenaltyController extends Controller
{
    use ApiResponseTrait;

    // GET /api/penalties
    public function index(Request $request)
    {
        $query = Penalty::with('contractor');

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where('reason', 'like', "%{$q}%")
                  ->orWhereHas('contractor', fn($qb) => $qb->where('name', 'like', "%{$q}%"));
        }

        $paginator = $query->latest()->paginate(15)->through(fn($p) => [
            'id'              => $p->id,
            'contractor_name' => $p->contractor?->name,
            'contractor_id'   => $p->contractor_id,
            'reason'          => $p->reason,
            'amount'          => $p->amount,
            'paid_amount'     => $p->paid_amount,
            'status'          => $p->status,
            'status_label'    => $p->status_label,
            'notes'           => $p->notes,
            'reject_reason'   => $p->reject_reason,
            'paid_at'         => $p->paid_at,
            'created_at'      => $p->created_at,
        ]);

        return $this->paginated($paginator);
    }

    // POST /api/penalties
    public function store(Request $request)
    {
        $validated = $request->validate([
            'contractor_id' => 'required|exists:contractors,id',
            'reason'        => 'required|string|max:500',
            'amount'        => 'required|numeric|min:0',
            'notes'         => 'nullable|string',
        ]);

        $penalty = Penalty::create($validated);

        // Eloquent::create() لا يُعيد قيم أعمدة القاعدة الافتراضية (status, paid_amount)
        // في الكائن بالذاكرة تلقائياً — refresh() يضمن أن الاستجابة تعكس الصف الفعلي بالقاعدة.
        $penalty->refresh();

        return $this->success(
            $penalty->load('contractor')->toArray(),
            'تم إضافة الغرامة بنجاح.',
            201,
        );
    }

    /**
     * PATCH /api/penalties/{id}/status
     * تحديث حالة الغرامة إلى إحدى الحالات الأربع المعتمَدة (REQ-06 #6) — يحل محل
     * markPaid السابقة التي كانت تدعم "مدفوع" فقط.
     */
    public function updateStatus(Request $request, Penalty $penalty)
    {
        $data = $request->validate([
            'status'         => 'required|in:unpaid,paid,partially_paid,rejected',
            'paid_amount'    => 'required_if:status,partially_paid|nullable|numeric|min:0.01',
            'reject_reason'  => 'nullable|string|max:500',
        ]);

        if ($data['status'] === 'partially_paid' && (float) $data['paid_amount'] >= (float) $penalty->amount) {
            return $this->error('المبلغ المسدَّد جزئياً يجب أن يكون أقل من مبلغ الغرامة الكامل — استخدم حالة "مسدَّدة" بدلاً من ذلك.', 422);
        }

        $update = ['status' => $data['status']];

        match ($data['status']) {
            'paid'           => $update += ['paid_amount' => $penalty->amount, 'paid_at' => now(), 'reject_reason' => null],
            'partially_paid' => $update += ['paid_amount' => $data['paid_amount'], 'paid_at' => null, 'reject_reason' => null],
            'rejected'       => $update += ['paid_amount' => 0, 'reject_reason' => $data['reject_reason'] ?? null, 'paid_at' => null],
            'unpaid'         => $update += ['paid_amount' => 0, 'paid_at' => null, 'reject_reason' => null],
        };

        $penalty->update($update);

        return $this->success($penalty->fresh('contractor')->toArray(), 'تم تحديث حالة الغرامة بنجاح.');
    }
}
