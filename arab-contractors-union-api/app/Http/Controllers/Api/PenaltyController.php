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
            'status'          => $p->status,
            'notes'           => $p->notes,
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

        return $this->success(
            $penalty->load('contractor')->toArray(),
            'تم إضافة الغرامة بنجاح.',
            201,
        );
    }

    // PATCH /api/penalties/{id}/pay
    public function markPaid(Penalty $penalty)
    {
        $penalty->update(['status' => 'paid', 'paid_at' => now()]);

        return $this->success(message: 'تم تسجيل الدفع بنجاح.');
    }
}
