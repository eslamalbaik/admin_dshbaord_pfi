<?php

namespace App\Services;

use App\Models\Contractor;
use App\Support\ContractorLookups;
use Mpdf\Mpdf;

/**
 * خدمة تصدير الملف الشخصي للمقاول كملف PDF — تعزل 193 سطر HTML/mPDF
 * من الكنترولر إلى مكان قابل للاختبار والصيانة.
 */
class ContractorProfilePdfService
{
    /**
     * يُنشئ ملف PDF للملف الشخصي ويُعيد محتواه كـ string.
     */
    public function generate(Contractor $contractor): string
    {
        $html = $this->buildHtml($contractor);

        $tempDir = storage_path('app/mpdf-tmp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'orientation'   => 'P',
            'margin_top'    => 12,
            'margin_right'  => 12,
            'margin_bottom' => 12,
            'margin_left'   => 12,
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
     * بناء HTML القالب — منفصل لسهولة التعديل والاختبار.
     */
    private function buildHtml(Contractor $contractor): string
    {
        $specialtiesRows = '';
        if (is_array($contractor->specialties)) {
            foreach ($contractor->specialties as $spec) {
                $fName = ContractorLookups::fieldName($spec['field_lk_type'] ?? null);
                $sName = ContractorLookups::specializationName($spec['specialization_lk_type'] ?? null);
                $grade = $spec['classification'] ?? 'غير محدد';
                $specialtiesRows .= "
                <tr>
                    <td>{$fName}</td>
                    <td>{$sName}</td>
                    <td><span class='badge-grade'>{$grade}</span></td>
                </tr>";
            }
        }

        if (empty($specialtiesRows)) {
            $specialtiesRows = '<tr><td colspan="3" style="text-align: center; color: #888;">لا يوجد اختصاصات مسجلة</td></tr>';
        }

        // تحضير الشركاء
        $partnersHtml = 'لا يوجد';
        if ($contractor->partners) {
            $partnersArr = is_array($contractor->partners) ? $contractor->partners : json_decode($contractor->partners, true);
            if (is_array($partnersArr) && ! empty($partnersArr)) {
                $partnersHtml = implode('، ', $partnersArr);
            } elseif (is_string($contractor->partners)) {
                $partnersHtml = $contractor->partners;
            }
        }

        $generatedAt = now()->format('Y-m-d H:i');
        $regDate = $contractor->registration_date ? $contractor->registration_date->format('Y-m-d') : 'غير محدد';
        $estDate = $contractor->established_date ? $contractor->established_date->format('Y-m-d') : ($contractor->established_year ?? 'غير محدد');

        return <<<HTML
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="UTF-8">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: dejavusans; font-size: 11px; color: #2C3E50; direction: rtl; line-height: 1.6; }
  .container { padding: 10px; }
  .header { border-bottom: 3px solid #1A237E; padding-bottom: 12px; margin-bottom: 20px; }
  .header-table { width: 100%; border-collapse: collapse; }
  .header-title { font-size: 20px; color: #1A237E; font-weight: bold; text-align: right; }
  .header-subtitle { font-size: 12px; color: #D4AF37; font-weight: bold; margin-top: 5px; }
  .meta-text { font-size: 9px; color: #7F8C8D; text-align: left; }
  
  .section-title { font-size: 13px; font-weight: bold; color: #1A237E; border-right: 4px solid #D4AF37; padding-right: 8px; margin: 15px 0 10px 0; }
  
  .info-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
  .info-table td { padding: 8px 10px; border: 1px solid #E0E0E0; font-size: 11px; }
  .info-label { font-weight: bold; color: #1A237E; background-color: #F5F6FA; width: 22%; }
  .info-value { width: 28%; background-color: #FFFFFF; }
  
  .data-table { width: 100%; border-collapse: collapse; margin-top: 5px; }
  .data-table th { background-color: #1A237E; color: #FFFFFF; padding: 8px 12px; font-size: 11px; text-align: right; font-weight: bold; }
  .data-table td { padding: 8px 12px; border-bottom: 1px solid #E0E0E0; font-size: 11px; text-align: right; }
  .data-table tr:nth-child(even) td { background-color: #F8F9FD; }
  .badge-grade { background-color: #E8EAF6; color: #1A237E; padding: 2px 8px; border-radius: 4px; font-weight: bold; }
  
  .footer { margin-top: 40px; border-top: 1px solid #E0E0E0; padding-top: 10px; font-size: 9px; color: #95A5A6; text-align: center; }
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <table class="header-table">
      <tr>
        <td>
          <div class="header-title">اتحاد المقاولين الفلسطينيين</div>
          <div class="header-subtitle">ملف معلومات الشركة المعتمد</div>
        </td>
        <td style="text-align: left; vertical-align: bottom;">
          <div class="meta-text">تاريخ التصدير: {$generatedAt}</div>
          <div class="meta-text">رقم العضوية: {$contractor->membership_number}</div>
        </td>
      </tr>
    </table>
  </div>

  <div class="section-title">البيانات العامة للشركة</div>
  <table class="info-table">
    <tr>
      <td class="info-label">اسم الشركة</td>
      <td class="info-value">{$contractor->name}</td>
      <td class="info-label">رقم العضوية</td>
      <td class="info-value">{$contractor->membership_number}</td>
    </tr>
    <tr>
      <td class="info-label">رقم السجل التجاري</td>
      <td class="info-value">{$contractor->commercial_register}</td>
      <td class="info-label">رقم الترخيص</td>
      <td class="info-value">{$contractor->license_number}</td>
    </tr>
    <tr>
      <td class="info-label">المفوض بالتوقيع</td>
      <td class="info-value">{$contractor->authorized_person}</td>
      <td class="info-label">اسم المالك</td>
      <td class="info-value">{$contractor->owner_name}</td>
    </tr>
    <tr>
      <td class="info-label">رقم الجوال</td>
      <td class="info-value">{$contractor->phone}</td>
      <td class="info-label">الفاكس</td>
      <td class="info-value">{$contractor->fax}</td>
    </tr>
    <tr>
      <td class="info-label">البريد الإلكتروني</td>
      <td class="info-value">{$contractor->email}</td>
      <td class="info-label">رأس المال</td>
      <td class="info-value">{$contractor->capital}</td>
    </tr>
    <tr>
      <td class="info-label">تاريخ التسجيل</td>
      <td class="info-value">{$regDate}</td>
      <td class="info-label">تاريخ التأسيس</td>
      <td class="info-value">{$estDate}</td>
    </tr>
    <tr>
      <td class="info-label">الشكل القانوني</td>
      <td class="info-value">{$contractor->legal_form}</td>
      <td class="info-label">المدينة / العنوان</td>
      <td class="info-value">{$contractor->city} - {$contractor->address}</td>
    </tr>
    <tr>
      <td class="info-label">الشركاء</td>
      <td colspan="3" class="info-value">{$partnersHtml}</td>
    </tr>
    <tr>
      <td class="info-label">غايات الشركة</td>
      <td colspan="3" class="info-value">{$contractor->company_purposes}</td>
    </tr>
  </table>

  <div class="section-title">المجالات والاختصاصات والدرجات</div>
  <table class="data-table">
    <thead>
      <tr>
        <th>المجال الرئيسي</th>
        <th>الاختصاص التفصيلي</th>
        <th>درجة التصنيف</th>
      </tr>
    </thead>
    <tbody>
      {$specialtiesRows}
    </tbody>
  </table>

  <div class="footer">
    تم إنشاء هذا المستند إلكترونياً وصادر عن منصة اتحاد المقاولين الفلسطينيين.
  </div>
</div>
</body>
</html>
HTML;
    }
}
