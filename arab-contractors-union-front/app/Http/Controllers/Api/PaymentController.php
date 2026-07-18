<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    // GET /api/payments/transactions
    public function index(Request $request)
    {
        $query = Payment::with('contractor');

        if ($request->filled('search')) {
            $q = $request->search;
            $query->whereHas('contractor', fn($qb) => $qb->where('name', 'like', "%{$q}%"));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $payments = $query->latest()->paginate(15)->through(function ($p) {
            return [
                'id'              => $p->id,
                'contractor'      => $p->contractor?->name,
                'contractor_id'   => $p->contractor_id,
                'amount'          => $p->amount,
                'type'            => $p->type,
                'status'          => $p->status,
                'method'          => $p->method,
                'reference_number'=> $p->reference_number,
                'paid_at'         => $p->paid_at,
                'created_at'      => $p->created_at,
            ];
        });

        return response()->json($payments);
    }

    // POST /api/payments/transactions
    public function store(Request $request)
    {
        $validated = $request->validate([
            'contractor_id'    => 'required|exists:contractors,id',
            'membership_id'    => 'nullable|exists:memberships,id',
            'amount'           => 'required|numeric|min:0',
            'type'             => 'nullable|string',
            'status'           => 'nullable|in:pending,paid,refunded,failed',
            'method'           => 'nullable|string',
            'reference_number' => 'nullable|string',
            'notes'            => 'nullable|string',
        ]);

        $payment = Payment::create($validated);

        return response()->json($payment, 201);
    }
}
