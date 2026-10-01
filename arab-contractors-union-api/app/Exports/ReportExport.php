<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ReportExport implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    public function __construct(
        private array  $data,
        private string $period,
        private int    $year,
        private int    $month,
    ) {}

    public function title(): string
    {
        return $this->period === 'monthly'
            ? $this->arabicMonth($this->month) . ' ' . $this->year
            : 'سنوي ' . $this->year;
    }

    public function array(): array
    {
        $typeLabels = [
            'membership_fee' => 'رسوم عضوية',
            'renewal_fee'    => 'رسوم تجديد',
            'other'          => 'أخرى',
        ];

        $periodLabel = $this->period === 'monthly'
            ? 'تقرير شهري — ' . $this->arabicMonth($this->month) . ' ' . $this->year
            : 'تقرير سنوي — ' . $this->year;

        $rows = [
            // العنوان
            [$periodLabel, ''],
            ['تاريخ الإنشاء', now()->format('Y-m-d H:i')],
            ['', ''],

            // ─── الملخص ───────────────────────────────────────────
            ['ملخص الفترة', ''],
            ['البيان', 'القيمة'],
            ['إجمالي الإيرادات', number_format((float)$this->data['revenue'], 2) . ' ₪'],
            ['مقاولون جدد', $this->data['newContractors']],
            ['عضويات نشطة', $this->data['activeMemberships']],
            ['عطاءات', $this->data['tenders']],
            ['', ''],

            // ─── الغرامات ─────────────────────────────────────────
            ['الغرامات والمخالفات', ''],
            ['البيان', 'القيمة'],
            ['غرامات صادرة', $this->data['penaltiesIssued'] . ' غرامة'],
            ['إجمالي قيمة الغرامات', number_format((float)$this->data['penaltiesAmount'], 2) . ' ₪'],
            ['الغرامات المدفوعة', number_format((float)$this->data['penaltiesPaid'], 2) . ' ₪'],
            ['الغرامات المعلقة', number_format(max(0, (float)$this->data['penaltiesAmount'] - (float)$this->data['penaltiesPaid']), 2) . ' ₪'],
            ['', ''],
        ];

        // ─── أنواع المدفوعات ─────────────────────────────────────
        if (!empty($this->data['paymentTypes'])) {
            $rows[] = ['توزيع أنواع المدفوعات', ''];
            $rows[] = ['النوع', 'الإجمالي'];
            foreach ($this->data['paymentTypes'] as $type => $total) {
                $rows[] = [$typeLabels[$type] ?? $type, number_format((float)$total, 2) . ' ₪'];
            }
            $rows[] = ['', ''];
        }

        // ─── الإيرادات الشهرية ───────────────────────────────────
        $monthNames = ['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];
        $rows[] = ['الإيرادات الشهرية', '', '', '', '', '', '', '', '', '', '', '', ''];
        $rows[] = $monthNames;
        $rows[] = array_map(fn($v) => number_format((float)$v, 0), $this->data['revenueChart']);

        // ─── رسوم التسجيل — أول انتساب (المادة 37) — للتقرير السنوي فقط ───
        if ($this->period === 'annual' && isset($this->data['registrationFees'])) {
            $rows[] = ['', ''];
            $rows[] = ['رسوم التسجيل — أول انتساب (المادة 37)', ''];
            $rows[] = ['البيان', 'القيمة'];
            $rows[] = ['عدد ذمم أول انتساب هذه السنة', $this->data['registrationFees']['dues_count'] . ' ذمة'];
            $rows[] = ['إجمالي رسوم التسجيل', number_format((float) $this->data['registrationFees']['total_jod'], 2) . ' د.أ'];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->setRightToLeft(true); // RTL direction

        return [
            // العنوان الرئيسي
            1 => [
                'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FF1565C0']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE3F2FD']],
            ],

            // رؤوس الأقسام (صف 4، 10، 16، إلخ)
            4  => ['font' => ['bold' => true, 'color' => ['argb' => 'FF1565C0']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFBBDEFB']]],
            5  => ['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1565C0']], 'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']]],
            11 => ['font' => ['bold' => true, 'color' => ['argb' => 'FF1565C0']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFBBDEFB']]],
            12 => ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1565C0']]],
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
