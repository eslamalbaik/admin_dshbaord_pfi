<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;

/**
 * إيصال قبض إلكتروني PDF — يُولَّد مرة واحدة عند تأكيد الدفعة (REQ-19) ويُخزَّن
 * على القرص العام، فيبقى ثابتاً كإثبات دفع رسمي بدل توليده من جديد كل مرة.
 * نفس نمط توليد PDF بـMpdf المستخدَم أصلاً بـContractorAuthController (ملف الشركة).
 */
class ReceiptPdfService
{
    public function generate(Payment $payment): string
    {
        $contractor = $payment->contractor;
        $reference  = 'RCPT-' . str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT);
        $currency   = $payment->currency ?? 'JOD';
        $issuedAt   = ($payment->paid_at ?? now())->format('Y/m/d H:i');

        $rateRow = $currency !== 'JOD' && $payment->exchange_rate
            ? "<tr><td class=\"info-label\">سعر الصرف</td><td class=\"info-value\">{$payment->exchange_rate} ({$payment->rate_source})</td></tr>"
            : '';

        $html = <<<HTML
<html dir="rtl" lang="ar">
<head>
<meta charset="utf-8">
<style>
  body { font-family: dejavusans; font-size: 13px; }
  .header { text-align: center; border-bottom: 2px solid #142850; padding-bottom: 10px; margin-bottom: 20px; }
  .header h1 { font-size: 18px; color: #142850; margin: 0; }
  .header h2 { font-size: 14px; color: #555; margin: 4px 0 0; }
  .watermark { position: fixed; top: 40%; left: 15%; font-size: 60px; color: #eee; transform: rotate(-25deg); z-index: -1; }
  table.info-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
  table.info-table td { padding: 8px; border: 1px solid #ddd; }
  .info-label { background: #f5f5f5; font-weight: bold; width: 30%; }
  .amount-box { text-align: center; margin: 25px 0; padding: 15px; background: #f0f4ff; border: 1px solid #142850; }
  .amount-box .value { font-size: 24px; font-weight: bold; color: #142850; }
  .footer { margin-top: 40px; font-size: 11px; color: #777; text-align: center; }
</style>
</head>
<body>
<div class="watermark">معتمد</div>
<div class="header">
  <h1>اتحاد المقاولين الفلسطينيين</h1>
  <h2>إيصال قبض إلكتروني</h2>
</div>

<table class="info-table">
  <tr>
    <td class="info-label">رقم الإيصال المرجعي</td>
    <td class="info-value">{$reference}</td>
  </tr>
  <tr>
    <td class="info-label">اسم المقاول</td>
    <td class="info-value">{$contractor?->name}</td>
  </tr>
  <tr>
    <td class="info-label">رقم العضوية</td>
    <td class="info-value">{$contractor?->membership_number}</td>
  </tr>
  <tr>
    <td class="info-label">رقم مرجع التحويل</td>
    <td class="info-value">{$payment->reference_number}</td>
  </tr>
  {$rateRow}
  <tr>
    <td class="info-label">تاريخ الإصدار</td>
    <td class="info-value">{$issuedAt}</td>
  </tr>
</table>

<div class="amount-box">
  <div>المبلغ المدفوع</div>
  <div class="value">{$payment->amount} {$currency}</div>
</div>

<div class="footer">
  تم إصدار هذا الإيصال إلكترونياً وهو معتمد رسمياً من اتحاد المقاولين الفلسطينيين — لا حاجة لتوقيع أو ختم يدوي.
</div>
</body>
</html>
HTML;

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

        $path = "receipts/receipt-{$payment->id}.pdf";
        Storage::disk('public')->put($path, $mpdf->Output('', 'S'));

        return $path;
    }
}
