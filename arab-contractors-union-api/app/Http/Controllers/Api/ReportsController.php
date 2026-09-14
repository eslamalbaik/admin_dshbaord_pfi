<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contractor;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Penalty;
use App\Models\Tender;
use App\Services\ReportPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ReportExport;

class ReportsController extends Controller
{
    // ─────────────────────────────────────────────────────────────
    //  GET /api/v1/reports/summary
    //  ?period=monthly&year=2026&month=6
    //  ?period=annual&year=2026
    // ─────────────────────────────────────────────────────────────
    public function summary(Request $request)
    {
        $period = $request->get('period', 'monthly'); // monthly | annual
        $year   = (int) $request->get('year', now()->year);
        $month  = (int) $request->get('month', now()->month);

        if ($period === 'monthly') {
            return response()->json($this->monthlySummary($year, $month));
        }

        return response()->json($this->annualSummary($year));
    }

    // ─────────────────────────────────────────────────────────────
    //  GET /api/v1/reports/export/pdf
    //  Uses mPDF — full Arabic shaping + RTL support
    // ─────────────────────────────────────────────────────────────
    public function exportPdf(Request $request, ReportPdfService $pdfService)
    {
        $period = $request->get('period', 'monthly');
        $year   = (int) $request->get('year', now()->year);
        $month  = (int) $request->get('month', now()->month);

        $data = $period === 'monthly'
            ? $this->monthlySummary($year, $month)
            : $this->annualSummary($year);

        $output = $pdfService->generate($data, $period, $year, $month);

        $filename = $period === 'monthly'
            ? "تقرير_{$year}_{$month}.pdf"
            : "تقرير_سنوي_{$year}.pdf";

        return response($output, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . urlencode($filename) . '"',
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    //  GET /api/v1/reports/export/excel
    // ─────────────────────────────────────────────────────────────
    public function exportExcel(Request $request)
    {
        $period = $request->get('period', 'monthly');
        $year   = (int) $request->get('year', now()->year);
        $month  = (int) $request->get('month', now()->month);

        $data = $period === 'monthly'
            ? $this->monthlySummary($year, $month)
            : $this->annualSummary($year);

        $filename = $period === 'monthly'
            ? "تقرير_{$year}_{$month}.xlsx"
            : "تقرير_سنوي_{$year}.xlsx";

        return Excel::download(
            new ReportExport($data, $period, $year, $month),
            $filename,
            \Maatwebsite\Excel\Excel::XLSX,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    // ─────────────────────────────────────────────────────────────
    //  Private: Monthly Summary
    // ─────────────────────────────────────────────────────────────
    private function monthlySummary(int $year, int $month): array
    {
        // إيرادات الشهر
        $revenue = Payment::where('status', 'paid')
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->sum('amount');

        // مقاولون جدد
        $newContractors = Contractor::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->count();

        // عضويات نشطة
        $activeMemberships = Membership::where('status', 'active')->count();

        // غرامات الشهر
        $penaltiesIssued = Penalty::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->count();

        $penaltiesAmount = Penalty::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->sum('amount');

        $penaltiesPaid = Penalty::where('status', 'paid')
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->sum('amount');

        // عطاءات
        $tenders = Tender::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->count();

        // إيرادات الـ 12 شهر الأخيرة للشارت
        $revenueChart = $this->last12MonthsRevenue();

        // مقاولون جدد الـ 12 شهر
        $contractorsChart = $this->last12MonthsContractors();

        // توزيع أنواع المدفوعات
        $paymentTypes = Payment::where('status', 'paid')
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->select('type', DB::raw('SUM(amount) as total'))
            ->groupBy('type')
            ->pluck('total', 'type')
            ->toArray();

        return compact(
            'revenue', 'newContractors', 'activeMemberships',
            'penaltiesIssued', 'penaltiesAmount', 'penaltiesPaid',
            'tenders', 'revenueChart', 'contractorsChart', 'paymentTypes'
        );
    }

    // ─────────────────────────────────────────────────────────────
    //  Private: Annual Summary
    // ─────────────────────────────────────────────────────────────
    private function annualSummary(int $year): array
    {
        // إيرادات السنة
        $revenue = Payment::where('status', 'paid')
            ->whereYear('created_at', $year)
            ->sum('amount');

        // مقاولون جدد
        $newContractors = Contractor::whereYear('created_at', $year)->count();

        // عضويات
        $activeMemberships = Membership::where('status', 'active')->count();

        // غرامات
        $penaltiesIssued = Penalty::whereYear('created_at', $year)->count();
        $penaltiesAmount = Penalty::whereYear('created_at', $year)->sum('amount');
        $penaltiesPaid   = Penalty::where('status', 'paid')
            ->whereYear('created_at', $year)->sum('amount');

        // عطاءات
        $tenders = Tender::whereYear('created_at', $year)->count();

        // إيرادات شهرياً للسنة
        $revenueChart = collect(range(1, 12))->map(function ($m) use ($year) {
            return (float) Payment::where('status', 'paid')
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $m)
                ->sum('amount');
        })->values()->toArray();

        // مقاولون جدد شهرياً
        $contractorsChart = collect(range(1, 12))->map(function ($m) use ($year) {
            return Contractor::whereYear('created_at', $year)
                ->whereMonth('created_at', $m)
                ->count();
        })->values()->toArray();

        // توزيع أنواع المدفوعات للسنة
        $paymentTypes = Payment::where('status', 'paid')
            ->whereYear('created_at', $year)
            ->select('type', DB::raw('SUM(amount) as total'))
            ->groupBy('type')
            ->pluck('total', 'type')
            ->toArray();

        // رسوم التسجيل (المادة 37) — ذمم أول سنة انتساب لهذه السنة تحديداً
        $registrationFees = \App\Models\ContractorDue::registrationFeesSummary($year);

        return compact(
            'revenue', 'newContractors', 'activeMemberships',
            'penaltiesIssued', 'penaltiesAmount', 'penaltiesPaid',
            'tenders', 'revenueChart', 'contractorsChart', 'paymentTypes',
            'registrationFees'
        );
    }

    // ─────────────────────────────────────────────────────────────
    //  آخر 12 شهر للشارت
    // ─────────────────────────────────────────────────────────────
    private function last12MonthsRevenue(): array
    {
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $months[] = (float) Payment::where('status', 'paid')
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->sum('amount');
        }
        return $months;
    }

    private function last12MonthsContractors(): array
    {
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $months[] = Contractor::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();
        }
        return $months;
    }

    private function arabicMonth(int $month): string
    {
        return [
            1=>'يناير',2=>'فبراير',3=>'مارس',4=>'أبريل',
            5=>'مايو',6=>'يونيو',7=>'يوليو',8=>'أغسطس',
            9=>'سبتمبر',10=>'أكتوبر',11=>'نوفمبر',12=>'ديسمبر',
        ][$month] ?? '';
    }
}
