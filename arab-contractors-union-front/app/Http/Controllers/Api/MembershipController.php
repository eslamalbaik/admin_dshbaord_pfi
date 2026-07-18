<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MembershipController extends Controller
{
    // GET /api/memberships/pending
    public function pending()
    {
        $memberships = Membership::with('contractor')
            ->where('status', 'pending')
            ->latest()
            ->get()
            ->map(function ($m) {
                return [
                    'id'              => $m->id,
                    'contractor_name' => $m->contractor?->name,
                    'type'            => $m->type,
                    'amount'          => $m->amount,
                    'document_url'    => $m->document_url,
                    'created_at'      => $m->created_at,
                ];
            });

        return response()->json($memberships);
    }

    // GET /api/memberships
    public function index(Request $request)
    {
        $query = Membership::with('contractor');

        if ($request->filled('contractor_id')) {
            $query->where('contractor_id', $request->contractor_id);
        }

        return response()->json($query->latest()->paginate(15));
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

        // تحديث حالة المقاول
        $membership->contractor()->update(['status' => 'active']);

        return response()->json(['message' => 'تمت الموافقة على العضوية']);
    }

    // POST /api/memberships/{id}/reject
    public function reject(Membership $membership)
    {
        $membership->update([
            'status'      => 'rejected',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return response()->json(['message' => 'تم رفض طلب العضوية']);
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

        return response()->json($membership, 201);
    }
}
