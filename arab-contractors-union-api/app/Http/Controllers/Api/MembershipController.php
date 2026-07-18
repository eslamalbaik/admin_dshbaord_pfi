<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Membership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MembershipController extends Controller
{
    use ApiResponseTrait;

    // GET /api/memberships/pending
    public function pending()
    {
        $memberships = Membership::with('contractor')
            ->where('status', 'pending')
            ->latest()
            ->get()
            ->map(fn($m) => [
                'id'              => $m->id,
                'contractor_name' => $m->contractor?->name,
                'type'            => $m->type,
                'amount'          => $m->amount,
                'document_url'    => $m->document_url,
                'created_at'      => $m->created_at,
            ]);

        return $this->success($memberships->toArray());
    }

    // GET /api/memberships
    public function index(Request $request)
    {
        $query = Membership::with('contractor');

        if ($request->filled('contractor_id')) {
            $query->where('contractor_id', $request->contractor_id);
        }

        return $this->paginated($query->latest()->paginate(15));
    }

    // POST /api/memberships
    public function store(Request $request)
    {
        $validated = $request->validate([
            'contractor_id' => 'required|exists:contractors,id',
            'type'          => 'required|in:new,renewal,upgrade',
            'amount'        => 'nullable|numeric|min:0',
            'document_url'  => 'nullable|string',
            'notes'         => 'nullable|string',
        ]);

        $membership = Membership::create($validated);

        return $this->success($membership->toArray(), 'تم إنشاء طلب العضوية بنجاح.', 201);
    }

    // POST /api/memberships/{id}/approve
    public function approve(Membership $membership)
    {
        $membership->update([
            'status'      => 'active',
            'starts_at'   => now(),
            'expires_at'  => now()->addYear(),
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        $membership->contractor()->update(['status' => 'active']);

        return $this->success(message: 'تمت الموافقة على العضوية.');
    }

    // POST /api/memberships/{id}/reject
    public function reject(Membership $membership)
    {
        $membership->update([
            'status'      => 'rejected',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return $this->success(message: 'تم رفض طلب العضوية.');
    }
}
