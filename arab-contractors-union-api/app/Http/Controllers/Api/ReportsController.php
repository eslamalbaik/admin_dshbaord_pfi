<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contractor;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Penalty;
use App\Models\Tender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
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
    public function exportPdf(Request $request)
    {
        $period = $request->get('period', 'monthly');
        $year   = (int) $request->get('year', now()->year);
        $month  = (int) $request->get('month', now()->month);

        $data = $period === 'monthly'
            ? $this->monthlySummary($year, $month)
            : $this->annualSummary($year);

        $periodLabel = $period === 'monthly'
            ? $this->arabicMonth($month) . ' ' . $year
            : 'السنة ' . $year;

        $typeLabels = [
            'membership_fee' => 'رسوم عضوية',
            'renewal_fee'    => 'رسوم تجديد',
            'other'          => 'أخرى',
        ];

        // بناء صفوف جدول أنواع المدفوعات
        $paymentTypeRows = '';
        foreach ($data['paymentTypes'] as $type => $total) {
            $label = $typeLabels[$type] ?? $type;
            $paymentTypeRows .= "<tr><td>{$label}</td><td>" . number_format((float)$total, 2) . " ₪</td></tr>";
        }

        // بناء صفوف الإيرادات الشهرية
        $months = ['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];
        $revenueHeaders = implode('', array_map(fn($m) => "<th>{$m}</th>", $months));
        $revenueValues  = implode('', array_map(fn($v) => '<td>' . number_format((float)$v, 0) . '</td>', $data['revenueChart']));

        $generatedAt = now()->format('Y-m-d H:i');
        $revenue     = number_format((float)$data['revenue'], 0);
        $penalties   = number_format((float)$data['penaltiesAmount'], 2);
        $penPaid     = number_format((float)$data['penaltiesPaid'], 2);
        $penPending  = number_format(max(0, (float)$data['penaltiesAmount'] - (float)$data['penaltiesPaid']), 2);

        $html = <<<HTML
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="UTF-8">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: dejavusans; font-size: 12px; color: #222; direction: rtl; }
  .header { border-bottom: 3px solid #1565C0; padding-bottom: 12px; margin-bottom: 18px; }
  .header h1 { font-size: 18px; color: #1565C0; font-weight: bold; }
  .header .sub { font-size: 11px; color: #555; margin-top: 3px; }
  .meta { font-size: 10px; color: #888; margin-top: 5px; }
  .kpi-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
  .kpi-table td { width: 25%; border: 1px solid #ddd; background: #F9FAFB; padding: 10px; text-align: center; vertical-align: middle; }
  .kpi-value { font-size: 16px; font-weight: bold; color: #1565C0; display: block; margin-bottom: 3px; }
  .kpi-label { font-size: 10px; color: #555; }
  .section-title { font-size: 13px; font-weight: bold; color: #1565C0; border-right: 4px solid #1565C0; padding-right: 8px; margin: 18px 0 8px; }
  .data-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; font-size: 11px; }
  .data-table th { background: #1565C0; color: #fff; padding: 7px 10px; text-align: right; }
  .data-table td { padding: 7px 10px; border-bottom: 1px solid #eee; text-align: right; }
  .data-table tr:nth-child(even) td { background: #f5f5f5; }
  .green { color: #2E7D32; font-weight: bold; }
  .red   { color: #C62828; font-weight: bold; }
  .blue  { color: #1565C0; font-weight: bold; }
  .footer { border-top: 1px solid #ddd; margin-top: 24px; padding-top: 8px; font-size: 10px; color: #aaa; text-align: center; }
</style>
</head>
<body>
<div class="header">
  <h1>اتحاد المقاولين العرب — تقرير إحصائي</h1>
  <div class="sub">{$periodLabel} &nbsp;|&nbsp; النوع: {$this->periodLabel($period)}</div>
  <div class="meta">تاريخ الإنشاء: {$generatedAt}</div>
</div>

<table class="kpi-table">
  <tr>
    <td><span class="kpi-value blue">{$revenue} ₪</span><span class="kpi-label">إجمالي الإيرادات</span></td>
    <td><span class="kpi-value blue">{$data['newContractors']}</span><span class="kpi-label">مقاولون جدد</span></td>
    <td><span class="kpi-value blue">{$data['activeMemberships']}</span><span class="kpi-label">عضويات نشطة</span></td>
    <td><span class="kpi-value blue">{$data['tenders']}</span><span class="kpi-label">عطاءات</span></td>
  </tr>
</table>

<div class="section-title">الغرامات والمخالفات</div>
<table class="data-table">
  <thead><tr><th>البيان</th><th>القيمة</th></tr></thead>
  <tbody>
    <tr><td>غرامات صادرة</td><td>{$data['penaltiesIssued']} غرامة</td></tr>
    <tr><td>إجمالي قيمة الغرامات</td><td class="blue">{$penalties} ₪</td></tr>
    <tr><td>الغرامات المدفوعة</td><td class="green">{$penPaid} ₪</td></tr>
    <tr><td>الغرامات المعلقة</td><td class="red">{$penPending} ₪</td></tr>
  </tbody>
</table>

HTML;

        if (!empty($data['paymentTypes'])) {
            $html .= <<<HTML
<div class="section-title">توزيع أنواع المدفوعات</div>
<table class="data-table">
  <thead><tr><th>نوع الدفع</th><th>الإجمالي</th></tr></thead>
  <tbody>{$paymentTypeRows}</tbody>
</table>
HTML;
        }

        $html .= <<<HTML
<div class="section-title">الإيرادات الشهرية (₪)</div>
<table class="data-table">
  <thead><tr>{$revenueHeaders}</tr></thead>
  <tbody><tr>{$revenueValues}</tr></tbody>
</table>

<div class="footer">تم إنشاء هذا التقرير تلقائياً بواسطة نظام اتحاد المقاولين العرب — {$generatedAt}</div>
</body>
</html>
HTML;

        // ── mPDF — دعم كامل للعربية ──────────────────────────────
        $tempDir = storage_path('app/mpdf-tmp');
        if (!is_dir($tempDir)) mkdir($tempDir, 0775, true);

        $mpdf = new Mpdf([
            'mode'         => 'utf-8',
            'format'       => 'A4',
            'orientation'  => 'P',
            'margin_top'   => 15,
            'margin_right' => 15,
            'margin_bottom'=> 15,
            'margin_left'  => 15,
            'tempDir'      => $tempDir,
            'default_font' => 'dejavusans',
        ]);

        $mpdf->SetDirectionality('rtl');
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont   = true;
        $mpdf->WriteHTML($html);

        $filename = $period === 'monthly'
            ? "تقرير_{$year}_{$month}.pdf"
            : "تقرير_سنوي_{$year}.pdf";

        $output = $mpdf->Output('', 'S'); // 'S' = return as string

        return response($output, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . urlencode($filename) . '"',
        ]);
    }

    private function periodLabel(string $period): string
    {
        return $period === 'monthly' ? 'تقرير شهري' : 'تقرير سنوي';
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

        return compact(
            'revenue', 'newContractors', 'activeMemberships',
            'penaltiesIssued', 'penaltiesAmount', 'penaltiesPaid',
            'tenders', 'revenueChart', 'contractorsChart', 'paymentTypes'
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

    // ─────────────────────────────────────────────────────────────
    //  بناء صفوف الـ CSV
    // ─────────────────────────────────────────────────────────────
    private function buildCsvRows(array $data, string $period, int $year, int $month): array
    {
        $label = $period === 'monthly'
            ? "تقرير شهري - {$this->arabicMonth($month)} {$year}"
            : "تقرير سنوي - {$year}";

        return [
            ["\xEF\xBB\xBF{$label}"],  // BOM for Arabic Excel
            [''],
            ['البيان', 'القيمة'],
            ['إجمالي الإيرادات', number_format($data['revenue'], 2) . ' ₪'],
            ['مقاولون جدد', $data['newContractors']],
            ['عضويات نشطة', $data['activeMemberships']],
            ['عطاءات', $data['tenders']],
            [''],
            ['الغرامات', ''],
            ['غرامات صادرة', $data['penaltiesIssued']],
            ['إجمالي الغرامات', number_format($data['penaltiesAmount'], 2) . ' ₪'],
            ['غرامات مدفوعة', number_format($data['penaltiesPaid'], 2) . ' ₪'],
            ['غرامات معلقة', number_format($data['penaltiesAmount'] - $data['penaltiesPaid'], 2) . ' ₪'],
            [''],
            ['تاريخ التوليد', now()->format('Y-m-d H:i')],
        ];
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
