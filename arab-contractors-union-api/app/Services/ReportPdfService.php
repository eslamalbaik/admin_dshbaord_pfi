<?php

namespace App\Services;

use Mpdf\Mpdf;

/**
 * خدمة تصدير التقارير الإحصائية كملف PDF —
 * تعزل 170+ سطر HTML/mPDF config من ReportsController.
 */
class ReportPdfService
{
    /**
     * يُنشئ PDF التقرير ويُعيد محتواه كـ string.
     *
     * @param  array  $data       بيانات التقرير (revenue, penalties, etc.)
     * @param  string $period     'monthly' | 'annual'
     * @param  int    $year
     * @param  int    $month
     * @return string  محتوى الـ PDF كنص ثنائي
     */
    public function generate(array $data, string $period, int $year, int $month): string
    {
        $html = $this->buildHtml($data, $period, $year, $month);

        $tempDir = storage_path('app/mpdf-tmp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'orientation'   => 'P',
            'margin_top'    => 15,
            'margin_right'  => 15,
            'margin_bottom' => 15,
            'margin_left'   => 15,
            'tempDir'       => $tempDir,
            'default_font'  => 'dejavusans',
        ]);

        $mpdf->SetDirectionality('rtl');
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont   = true;
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    /**
     * بناء HTML القالب.
     */
    private function buildHtml(array $data, string $period, int $year, int $month): string
    {
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
            $paymentTypeRows .= "<tr><td>{$label}</td><td>" . number_format((float) $total, 2) . " ₪</td></tr>";
        }

        // بناء صفوف الإيرادات الشهرية
        $months = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
        $revenueHeaders = implode('', array_map(fn($m) => "<th>{$m}</th>", $months));
        $revenueValues  = implode('', array_map(fn($v) => '<td>' . number_format((float) $v, 0) . '</td>', $data['revenueChart']));

        $generatedAt    = now()->format('Y-m-d H:i');
        $revenue        = number_format((float) $data['revenue'], 0);
        $penalties      = number_format((float) $data['penaltiesAmount'], 2);
        $penPaid        = number_format((float) $data['penaltiesPaid'], 2);
        $penPending     = number_format(max(0, (float) $data['penaltiesAmount'] - (float) $data['penaltiesPaid']), 2);
        $periodTypeLabel = $period === 'monthly' ? 'تقرير شهري' : 'تقرير سنوي';

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
  <div class="sub">{$periodLabel} &nbsp;|&nbsp; النوع: {$periodTypeLabel}</div>
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

        if (! empty($data['paymentTypes'])) {
            $html .= <<<HTML
<div class="section-title">توزيع أنواع المدفوعات</div>
<table class="data-table">
  <thead><tr><th>نوع الدفع</th><th>الإجمالي</th></tr></thead>
  <tbody>{$paymentTypeRows}</tbody>
</table>
HTML;
        }

        if ($period === 'annual' && isset($data['registrationFees'])) {
            $regCount = $data['registrationFees']['dues_count'];
            $regTotal = number_format((float) $data['registrationFees']['total_jod'], 2);
            $html .= <<<HTML
<div class="section-title">رسوم التسجيل — أول انتساب (المادة 37)</div>
<table class="data-table">
  <thead><tr><th>البيان</th><th>القيمة</th></tr></thead>
  <tbody>
    <tr><td>عدد ذمم أول انتساب هذه السنة</td><td>{$regCount} ذمة</td></tr>
    <tr><td>إجمالي رسوم التسجيل</td><td class="blue">{$regTotal} د.أ</td></tr>
  </tbody>
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

        return $html;
    }

    private function arabicMonth(int $month): string
    {
        return [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
            5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
        ][$month] ?? '';
    }
}
