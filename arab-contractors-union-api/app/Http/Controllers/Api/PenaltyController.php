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

        $paginator = $query->latest()->paginate(min($request->integer('per_page', 15), 100))->through(fn($p) => [
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

    /**
     * POST /api/penalties
     * يقبل الحالة عند الإنشاء (TASK-17 #8) — الغرض تسجيل غرامات قائمة على الورق بحالتها
     * الفعلية بخطوة واحدة، بدل إنشائها "غير مسدَّدة" ثم نداء PATCH لتصحيحها.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'contractor_id' => 'required|exists:contractors,id',
            'reason'        => 'required|string|max:500',
            'amount'        => 'required|numeric|min:0',
            'notes'         => 'nullable|string',
            'status'        => 'nullable|in:unpaid,paid,partially_paid,rejected',
            'paid_amount'   => 'required_if:status,partially_paid|nullable|numeric|min:0.01',
            'reject_reason' => 'nullable|string|max:500',
        ]);

        $status = $validated['status'] ?? 'unpaid';

        if ($status === 'partially_paid' && (float) $validated['paid_amount'] >= (float) $validated['amount']) {
            return $this->error('المبلغ المسدَّد جزئياً يجب أن يكون أقل من مبلغ الغرامة الكامل — استخدم حالة "مسدَّدة" بدلاً من ذلك.', 422);
        }

        $penalty = Penalty::create(
            collect($validated)->only(['contractor_id', 'reason', 'amount', 'notes'])->all()
            + Penalty::attributesForStatus(
                $status,
                (float) $validated['amount'],
                isset($validated['paid_amount']) ? (float) $validated['paid_amount'] : null,
                $validated['reject_reason'] ?? null,
            ),
        );

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

        $penalty->update(Penalty::attributesForStatus(
            $data['status'],
            (float) $penalty->amount,
            isset($data['paid_amount']) ? (float) $data['paid_amount'] : null,
            $data['reject_reason'] ?? null,
        ));

        return $this->success($penalty->fresh('contractor')->toArray(), 'تم تحديث حالة الغرامة بنجاح.');
    }
}
