<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Contractor;
use App\Models\Membership;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    use ApiResponseTrait;

    public function index(): JsonResponse
    {
        session_write_close();

        $stats = Cache::remember('union_dashboard_stats', 120, function () {
            return [
                'total_contractors'  => Contractor::count(),
                'active_memberships' => Membership::where('status', 'active')->count(),
                'pending_requests'   => Membership::where('status', 'pending')->count(),
                'total_revenue'      => (float) Payment::where('status', 'paid')->sum('amount'),
                'expiring_soon'      => Membership::where('status', 'active')
                                            ->whereBetween('expires_at', [now(), now()->addDays(30)])
                                            ->count(),
            ];
        });

        $latestContractors = Cache::remember('union_latest_contractors', 60, function () {
            return Contractor::latest()
                ->limit(6)
                ->get(['id', 'name', 'email', 'status', 'created_at'])
                ->toArray();
        });

        $latestPayments = Cache::remember('union_latest_payments', 60, function () {
            return Payment::with('contractor')
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
        });

        $revenueChart = Cache::remember('union_revenue_chart', 300, function () {
            return Payment::where('status', 'paid')
                ->where('created_at', '>=', now()->subMonths(12))
                ->selectRaw('MONTH(created_at) as month, YEAR(created_at) as year, SUM(amount) as total')
                ->groupByRaw('YEAR(created_at), MONTH(created_at)')
                ->orderByRaw('YEAR(created_at), MONTH(created_at)')
                ->pluck('total')
                ->toArray();
        });

        return $this->success([
            'stats'             => $stats,
            'latestContractors' => $latestContractors,
            'latestPayments'    => $latestPayments,
            'revenueChart'      => $revenueChart,
        ]);
    }
}
