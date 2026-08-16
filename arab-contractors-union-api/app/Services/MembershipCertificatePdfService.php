<?php

namespace App\Services;

use App\Models\CertificateRequest;
use App\Support\ContractorLookups;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;

/**
 * توليد PDF شهادة العضوية (REQ-02) بمحرّك mPDF — بديل مسار LibreOffice.
 *
 * لماذا mPDF وليس تحويل DOCX عبر LibreOffice:
 *   سيرفر cPanel الإنتاجي لا يحوي soffice ولا تُمكن إضافته بلا صلاحيات root،
 *   فمسار DOCX→PDF يفشل هناك حتماً. mPDF حزمة PHP خالصة داخل vendor/.
 *   قياساً: 68 م.ب و11 ثانية مقابل 293 م.ب و24 ثانية لكل شهادة.
 *
 * الترويسة الرسمية:
 *   الأصول الثلاثة مستخرَجة من نفس قالب Word المعتمد (resources/certificate-assets)،
 *   فتبقى الهوية البصرية مطابقة للمصدر. تُركَّب عبر SetHTMLHeader/SetHTMLFooter
 *   لا عبر SetWatermarkImage — لأن الترويسة شريط نسبته 4:1 والتذييل 6.5:1، وتمطيط
 *   أيّهما ليملأ صفحة A4 يشوّه الشعار. الهوامش تحجز لهما المساحة.
 *
 * ملاحظة: التوليد يستغرق ثوانيَ عدة، فلا يجوز استدعاؤه داخل طلب HTTP متزامن.
 */
class MembershipCertificatePdfService
{
    /** ارتفاع الترويسة والتذييل بالمليمتر — مشتق من نسبة أبعاد الصورتين على عرض A4 المطبوع (180mm) */
    private const HEADER_HEIGHT_MM = 45;
    private const FOOTER_HEIGHT_MM = 28;

    /**
     * @param  bool  $preview  يكتب في certificates/preview بدل مسار الشهادة الرسمي.
     *                         بدونه، أي توليد اختباري يدهس شهادة صادرة تحمل نفس الرقم.
     */
    public function generate(CertificateRequest $certRequest, array $overrides = [], bool $preview = false): string
    {
        $contractor = $certRequest->contractor;
        $membership = $contractor->activeMembership;

        $serial   = 'MC-' . str_pad((string) $certRequest->id, 6, '0', STR_PAD_LEFT);
        $issuedAt = now()->format('Y-m-d');

        $decisionDate   = $overrides['decision_date']
            ?? ($contractor->classification_decision_date?->format('Y-m-d') ?? '—');
        $decisionNumber = $overrides['decision_number']
            ?? ($contractor->classification_decision_number ?: '—');
        $validUntil = $membership?->expires_at?->format('Y-m-d') ?? now()->addYear()->format('Y-m-d');

        $address = $overrides['address']
            ?? ($contractor->city ?: $contractor->governorate?->name ?: $contractor->address ?: '—');

        // القالب الأصلي لا يكتب "شركة/" ثابتة — نتجنّب تكرارها لو كان الاسم حاملاً لها
        $companyLabel = str_contains($contractor->name, 'شركة')
            ? $contractor->name
            : "شركة/ {$contractor->name}";

        $specialtiesRows = '';
        foreach (ContractorLookups::buildFieldsTree($contractor->specialties) as $field) {
            foreach ($field['specializations'] as $spec) {
                $grade = $spec['grade_label'] ?? $spec['grade'] ?? '—';
                $specialtiesRows .= '<tr>'
                    . '<td>' . e($field['field_name']) . '</td>'
                    . '<td>' . e($spec['spec_name']) . '</td>'
                    . '<td>' . e($grade) . '</td>'
                    . '</tr>';
            }
        }
        if ($specialtiesRows === '') {
            $specialtiesRows = '<tr><td colspan="3" style="text-align:center;color:#888;">لا يوجد تخصصات مسجّلة</td></tr>';
        }

        $presidentName   = \App\Models\Setting::get('union_president_name', 'المهندس/ سهيل هاشم السقا');
        $presidentTitle1 = \App\Models\Setting::get('union_president_title1', 'نقيب المقاولين الفلسطينيين');
        $presidentTitle2 = \App\Models\Setting::get('union_president_title2', 'النائب الأول لرئيس الاتحاد');

        $assets   = resource_path('certificate-assets');
        $headerImg = $assets . DIRECTORY_SEPARATOR . 'letterhead-header.jpg';
        $footerImg = $assets . DIRECTORY_SEPARATOR . 'letterhead-footer.png';
        $sealImg   = $assets . DIRECTORY_SEPARATOR . 'union-seal.jpg';

        $companyLabelEsc  = e($companyLabel);
        $addressEsc       = e($address);
        $membershipNumber = e($contractor->membership_number);

        $html = <<<HTML
<style>
  body { font-family: xbriyaz; direction: rtl; color: #111; font-size: 14px; line-height: 2.1; }
  .meta, .validity, .sign-text { font-family: xbriyaz; }
  table.data-table th, table.data-table td { font-family: xbriyaz; }
  .meta { width: 100%; margin-bottom: 18px; font-weight: bold; font-size: 13px; }
  .title {
    text-align: center; font-size: 26px; font-weight: bold;
    margin: 10px 0 26px 0; text-decoration: underline;
  }
  .body-text { font-size: 15px; text-align: justify; margin: 0 25px; line-height: 2.4; }
  .highlight { font-weight: bold; }
  table.data-table { width: 92%; margin: 26px auto; border-collapse: collapse; }
  table.data-table th, table.data-table td {
    border: 1px solid #000; padding: 8px; font-size: 14px; text-align: center; font-weight: bold;
  }
  .validity { text-align: center; font-size: 14px; font-weight: bold; margin-top: 26px; }
  .sign-block { width: 100%; margin-top: 30px; }
  .sign-block td { vertical-align: middle; }
  .sign-text { text-align: center; font-size: 14px; font-weight: bold; line-height: 1.9; }
  .seal-cell { text-align: center; }
</style>

<table class="meta">
  <tr>
    <td style="text-align:right;">الرقـم: {$serial}</td>
    <td style="text-align:left;">التاريخ: {$issuedAt}</td>
  </tr>
</table>

<div class="title">شهادة عضـــــــــوية</div>

<div class="body-text">
  يـــــــــشهد اتحــــــــاد المقـــــــاولين الفلــــــــسطينيين بأن
  <span class="highlight">{$companyLabelEsc}</span>
  وعنوانــــها <span class="highlight">{$addressEsc}</span>
  عضوا في الاتحاد تحت رقم <span class="highlight">{$membershipNumber}</span>،
  ومصنفة فـــي المجــالات والتخصــصات التاليــة بمـوجب قـرار لـجنة التصنيف الوطنية
  رقم (<span class="highlight">{$decisionNumber}</span>) بتاريخ
  <span class="highlight">{$decisionDate}م</span>
</div>

<table class="data-table">
  <thead>
    <tr><th>المجال</th><th>التخصص</th><th>الدرجة</th></tr>
  </thead>
  <tbody>{$specialtiesRows}</tbody>
</table>

<div class="validity">
  هذه الشهادة سارية المفعول حتى تاريخ {$validUntil}م وبعدها تعتبر لاغيه.
</div>

<table class="sign-block" autosize="1">
  <tr>
    <td width="50%" class="sign-text">
      {$presidentName}<br>
      {$presidentTitle1}<br>
      {$presidentTitle2}
    </td>
    <td width="50%" class="seal-cell">
      <img src="{$sealImg}" width="120" height="119">
    </td>
  </tr>
</table>
HTML;

        $tempDir = storage_path('app/mpdf-tmp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $headerH = self::HEADER_HEIGHT_MM;
        $footerH = self::FOOTER_HEIGHT_MM;

        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'orientation'   => 'P',
            'margin_left'   => 15,
            'margin_right'  => 15,
            // الهامش العلوي/السفلي يبدأ بعد الترويسة/التذييل، وmargin_header/footer
            // يحدّدان بعدهما عن حافة الورقة
            'margin_top'    => $headerH + 8,
            'margin_bottom' => $footerH + 8,
            'margin_header' => 8,
            'margin_footer' => 8,
            'tempDir'       => $tempDir,

            // xbriyaz خط نسخي عربي مشحون داخل mpdf/mpdf/ttfonts، فيعمل على أي
            // سيرفر بلا خطوط نظام. لا نشحن Amiri ولا Noto Naskh: كلاهما يحوي
            // GPOS Lookup Type 5 Format 3 وهو غير مدعوم في محلّل OTL بـ mPDF،
            // فيرمي FontException. تعطيل useOTL يتفاداه لكنه يكسر ربط الحروف.
            'default_font'  => 'xbriyaz',
        ]);

        $mpdf->SetDirectionality('rtl');
        // autoLangToFont يبدّل الخط تلقائياً حسب السكربت المكتشَف فيتجاوز xbriyaz
        // المحدَّد في CSS. نعطّله؛ ربط الحروف العربية يبقى سليماً لأنه من useOTL
        // المسجَّل أصلاً في تعريف xbriyaz لا من هذا الخيار.
        $mpdf->autoScriptToLang = false;
        $mpdf->autoLangToFont   = false;

        // الترويسة والتذييل الرسميان — مستخرَجان من قالب Word المعتمد، بعرض كامل
        // ونسبة أبعاد محفوظة (لا تمطيط)
        $mpdf->SetHTMLHeader('<img src="' . $headerImg . '" style="width:100%;">');
        $mpdf->SetHTMLFooter('<img src="' . $footerImg . '" style="width:100%;">');

        $mpdf->WriteHTML($html);

        $path = $preview
            ? "certificates/preview/{$serial}.pdf"
            : "certificates/membership/{$serial}.pdf";

        Storage::disk('public')->put($path, $mpdf->Output('', 'S'));

        return $path;
    }
}
