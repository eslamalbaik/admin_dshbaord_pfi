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

    /**
     * نسبة التغيّر % بين قيمتين — تُستخدم لكل بطاقات إحصائيات اللوحة الرئيسية
     * بدل الأرقام الثابتة (+5.2% وغيرها) اللي كانت من قالب Vuexy الأصلي.
     */
    private function percentChange(int|float $current, int|float $previous): float
    {
        if ($previous == 0)
            return $current > 0 ? 100.0 : 0.0;

        return round((($current - $previous) / $previous) * 100, 1);
    }

    public function index(): JsonResponse
    {
        session_write_close();

        $stats = Cache::remember('union_dashboard_stats', 120, function () {
            $cutoff = now()->subDays(30);

            $totalContractors    = Contractor::count();
            $activeMemberships   = Membership::where('status', 'active')->count();
            $pendingRequests     = Membership::where('status', 'pending')->count();
            $totalRevenue        = (float) Payment::where('status', 'paid')->sum('amount');
            $expiringSoon        = Membership::where('status', 'active')
                                        ->whereBetween('expires_at', [now(), now()->addDays(30)])
                                        ->count();
            // نفس شرط Contractor::getHasAppAccountAttribute — عدد المقاولين اللي فعلاً
            // فاتحوا حساب على تطبيق الموبايل (أكملوا التحقق وضبطوا كلمة مرور).
            $appUsers             = Contractor::whereNotNull('password')
                                        ->whereNotNull('phone_verified_at')
                                        ->count();

            // القيم المرجعية (منذ 30 يوماً) لحساب نسبة النمو — سجلات أُنشئت قبل هذا التاريخ.
            // بالنسبة لـ"عضويات نشطة"/"طلبات معلّقة"/"مستخدمو التطبيق" هذا تقريب (حالة
            // الصف الآن مقابل تاريخ إنشائه، لا لقطة تاريخية فعلية لحالته وقتها) — كافٍ لمؤشر
            // اتجاه عام لكن لا يُعتمد عليه كأرشيف تدقيق دقيق.
            $totalContractorsPrev  = Contractor::where('created_at', '<=', $cutoff)->count();
            $activeMembershipsPrev = Membership::where('status', 'active')->where('created_at', '<=', $cutoff)->count();
            $pendingRequestsPrev   = Membership::where('status', 'pending')->where('created_at', '<=', $cutoff)->count();
            $expiringSoonPrev      = Membership::where('status', 'active')
                                        ->where('created_at', '<=', $cutoff)
                                        ->whereBetween('expires_at', [$cutoff, $cutoff->copy()->addDays(30)])
                                        ->count();
            $appUsersPrev          = Contractor::whereNotNull('password')
                                        ->whereNotNull('phone_verified_at')
                                        ->where('phone_verified_at', '<=', $cutoff)
                                        ->count();

            // الإيرادات: مقارنة تدفق آخر 30 يوم بالـ30 يوم اللي قبلها (لا بالمجموع التراكمي
            // المعروض) — أدل على اتجاه الإيرادات الفعلي من مقارنة رقم تراكمي بنفسه سابقاً.
            $revenueLast30    = (float) Payment::where('status', 'paid')->where('created_at', '>=', $cutoff)->sum('amount');
            $revenuePrev30    = (float) Payment::where('status', 'paid')
                                    ->whereBetween('created_at', [$cutoff->copy()->subDays(30), $cutoff])
                                    ->sum('amount');

            return [
                'total_contractors'  => $totalContractors,
                'active_memberships' => $activeMemberships,
                'pending_requests'   => $pendingRequests,
                'total_revenue'      => $totalRevenue,
                'expiring_soon'      => $expiringSoon,
                'app_users'          => $appUsers,
                'changes'            => [
                    'total_contractors'  => $this->percentChange($totalContractors, $totalContractorsPrev),
                    'active_memberships' => $this->percentChange($activeMemberships, $activeMembershipsPrev),
                    'pending_requests'   => $this->percentChange($pendingRequests, $pendingRequestsPrev),
                    'total_revenue'      => $this->percentChange($revenueLast30, $revenuePrev30),
                    'expiring_soon'      => $this->percentChange($expiringSoon, $expiringSoonPrev),
                    'app_users'          => $this->percentChange($appUsers, $appUsersPrev),
                ],
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
