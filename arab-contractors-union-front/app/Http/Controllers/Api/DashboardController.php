<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contractor;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Inspection;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats()
    {
        session_write_close();

        $stats = [
            'total_contractors'  => Contractor::count(),
            'active_memberships' => Membership::where('status', 'active')->count(),
            'pending_requests'   => Membership::where('status', 'pending')->count(),
            'total_revenue'      => Payment::where('status', 'paid')->sum('amount'),
            'expiring_soon'      => Membership::where('status', 'active')
                                        ->whereBetween('expires_at', [now(), now()->addDays(30)])
                                        ->count(),
            'inspections_today'  => Inspection::whereDate('scheduled_at', today())->count(),
        ];

        $latestContractors = Contractor::latest()
            ->limit(6)
            ->get(['id', 'name', 'email', 'trade', 'status', 'created_at'])
            ->toArray();

        $latestPayments = Payment::with('contractor')
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn($p) => [
                'id'         => $p->id,
                'contractor' => $p->contractor?->name,
                'amount'     => $p->amount,
                'type'       => $p->type,
                'status'     => $p->status,
                'created_at' => $p->created_at,
            ])
            ->toArray();

        // إيرادات 12 شهر ماضية
        $revenueChart = Payment::where('status', 'paid')
            ->where('created_at', '>=', now()->subMonths(12))
            ->selectRaw('MONTH(created_at) as month, YEAR(created_at) as year, SUM(amount) as total')
            ->groupByRaw('YEAR(created_at), MONTH(created_at)')
            ->orderByRaw('YEAR(created_at), MONTH(created_at)')
            ->pluck('total')
            ->toArray();

        return response()->json([
            'stats'              => $stats,
            'latestContractors'  => $latestContractors,
            'latestPayments'     => $latestPayments,
            'revenueChart'       => $revenueChart,
        ]);
    }
}
