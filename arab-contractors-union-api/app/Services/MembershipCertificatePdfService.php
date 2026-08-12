<?php

namespace App\Services;

use App\Models\CertificateRequest;
use App\Support\ContractorLookups;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;

/**
 * توليد PDF شهادة العضوية تلقائياً (REQ-02) — نفس صيغة النموذج الرسمي الذي زوّده
 * الاتحاد (شهادة عضوية الخيسي 31-12)، مبني بنفس نمط Mpdf/RTL المستخدَم أصلاً
 * بتصدير ملف الشركة (ContractorAuthController::exportPdf).
 */
class MembershipCertificatePdfService
{
    public function generate(CertificateRequest $certRequest): string
    {
        $contractor = $certRequest->contractor;
        $membership = $contractor->activeMembership;

        $serial   = 'MC-' . str_pad((string) $certRequest->id, 6, '0', STR_PAD_LEFT);
        $issuedAt = now()->format('Y-m-d');
        $decisionDate = $contractor->classification_decision_date?->format('Y-m-d') ?? '—';
        $decisionNumber = $contractor->classification_decision_number ?: '—';
        $validUntil = $membership?->expires_at?->format('Y-m-d') ?? now()->addYear()->format('Y-m-d');

        $address = $contractor->city ?: $contractor->governorate?->name ?: $contractor->address ?: '—';

        $specialtiesRows = '';
        foreach (ContractorLookups::buildFieldsTree($contractor->specialties) as $field) {
            foreach ($field['specializations'] as $spec) {
                $grade = $spec['grade_label'] ?? $spec['grade'] ?? '—';
                $specialtiesRows .= "
                <tr>
                    <td>{$field['field_name']}</td>
                    <td>{$spec['spec_name']}</td>
                    <td>{$grade}</td>
                </tr>";
            }
        }
        if (empty($specialtiesRows)) {
            $specialtiesRows = '<tr><td colspan="3" style="text-align:center;color:#888;">لا يوجد تخصصات مسجّلة</td></tr>';
        }

        $presidentName  = \App\Models\Setting::get('union_president_name', 'المهندس/ سهيل هاشم السقا');
        $presidentTitle1 = \App\Models\Setting::get('union_president_title1', 'نقيب المقاولين الفلسطينيين');
        $presidentTitle2 = \App\Models\Setting::get('union_president_title2', 'النائب الأول لرئيس الاتحاد');

        $html = <<<HTML
<html dir="rtl" lang="ar">
<head>
<meta charset="utf-8">
<style>
  @page {
    margin: 35px;
    border: 3px double #1A237E;
    padding: 20px;
    background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%"><rect width="100%" height="100%" fill="none" stroke="%23e0e0e0" stroke-width="1" /></svg>');
  }
  body { 
    font-family: dejavusans; 
    font-size: 15px; 
    direction: rtl; 
    line-height: 2.2; 
    color: #111;
  }
  .header-table { width: 100%; margin-bottom: 30px; }
  .header-table td { font-size: 14px; font-weight: bold; }
  
  .title { 
    text-align: center; 
    font-size: 28px; 
    font-weight: bold; 
    color: #000; 
    margin: 30px 0 40px 0; 
    text-decoration: underline;
  }
  
  .body-text { 
    font-size: 16px; 
    text-align: justify; 
    text-justify: kashida; 
    margin: 0 40px; 
    line-height: 2.5;
  }
  
  .highlight { 
    font-weight: bold; 
    font-size: 17px;
  }
  
  table.data-table { 
    width: 90%; 
    margin: 40px auto; 
    border-collapse: collapse; 
  }
  table.data-table th, table.data-table td { 
    border: 1px solid #000; 
    padding: 10px; 
    font-size: 15px; 
    text-align: center; 
    font-weight: bold;
  }
  
  .validity { 
    text-align: center; 
    font-size: 15px; 
    font-weight: bold; 
    margin-top: 40px; 
  }
  
  .signatures { 
    width: 100%; 
    margin-top: 80px; 
  }
  .signatures td {
    text-align: left; 
    padding-left: 60px; 
    font-size: 15px; 
    font-weight: bold;
    line-height: 1.8;
  }
</style>
</head>
<body>

<table class="header-table">
  <tr>
    <td style="text-align:right;">الرقـم: {$serial}</td>
    <td style="text-align:left;">التاريخ: {$issuedAt}</td>
  </tr>
</table>

<div class="title">شهادة عضـــــــــوية</div>

<div class="body-text">
  يـــــــــشهد اتحــــــــاد المقـــــــاولين الفلــــــــسطينيين بأن شــركـــة / <span class="highlight">{$contractor->name}</span> وعنوانــــها / <span class="highlight">{$address}</span><br>
  عضوا في الاتحاد تحت رقم <span class="highlight">{$contractor->membership_number}</span><br>
  ومصنفة فـــي المجــالات والتخصــصات التاليــة بمـوجب قـرار لـجنة التصنيف الوطنية رقم (<span class="highlight">{$decisionNumber}</span>) بتاريخ : <span class="highlight">{$decisionDate}م</span>
</div>

<table class="data-table">
  <thead>
    <tr>
      <th>المجال</th>
      <th>التخصص</th>
      <th>الدرجة</th>
    </tr>
  </thead>
  <tbody>{$specialtiesRows}</tbody>
</table>

<div class="validity">
  هذه الشهادة سارية المفعول حتى تاريخ {$validUntil}م وبعدها تعتبر لاغيه.
</div>

<table class="signatures">
  <tr>
    <td>
      {$presidentName}<br>
      {$presidentTitle1}<br>
      {$presidentTitle2}
    </td>
  </tr>
</table>

</body>
</html>
HTML;

        $tempDir = storage_path('app/mpdf-tmp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

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

        $path = "certificates/membership/{$serial}.pdf";
        Storage::disk('public')->put($path, $mpdf->Output('', 'S'));

        return $path;
    }
}
