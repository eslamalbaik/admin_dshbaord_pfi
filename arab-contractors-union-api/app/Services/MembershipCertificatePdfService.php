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
        // صيغ التواريخ مطابقة للقالب: الترويسة d-m-Y، ومتن الشهادة d/m/Y
        $issuedAt = now()->format('d-m-Y');

        $decisionDate   = $overrides['decision_date']
            ?? ($contractor->classification_decision_date?->format('d/m/Y') ?? '—');
        $decisionNumber = $overrides['decision_number']
            ?? ($contractor->classification_decision_number ?: '—');
        $validUntil = $membership?->expires_at?->format('d/m/Y') ?? now()->addYear()->format('d/m/Y');

        $address = $overrides['address']
            ?? ($contractor->city ?: $contractor->governorate?->name ?: $contractor->address ?: '—');

        // القالب الأصلي لا يكتب "شركة/" ثابتة — نتجنّب تكرارها لو كان الاسم حاملاً لها
        $companyLabel = str_contains($contractor->name, 'شركة')
            ? $contractor->name
            : "شركة/ {$contractor->name}";

        $specialtiesRows = '';
        $rowCount        = 0;
        foreach (ContractorLookups::buildFieldsTree($contractor->specialties) as $field) {
            foreach ($field['specializations'] as $spec) {
                $rowCount++;
                // القالب المعتمد يكتب الدرجة بصيغتها المختصرة ("أولى أ") لا بمسمّى
                // العرض في اللوحة ("الدرجة الأولى (1)")، فنقدّم القيمة الخام.
                $grade = $spec['grade'] ?? $spec['grade_label'] ?? '—';
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

        // الشهادة يجب أن تبقى صفحة واحدة. كتلة التوقيع (الختم + الأسماء) لا تقبل
        // التقسيم في mPDF، فإن لم يتبقَّ لها ارتفاع كافٍ قفزت لصفحة ثانية بالكامل.
        // قياساً: 16pt يتّسع حتى 4 صفوف فقط، لذا نصغّر الجدول تدريجياً بعدها.
        [$tableFontPt, $tableCellPad] = match (true) {
            $rowCount >= 15 => ['5pt', '0.3pt'],
            $rowCount >= 13 => ['6pt', '0.3pt'],
            $rowCount >= 11 => ['7pt', '0.5pt'],
            $rowCount >= 9  => ['8pt', '0.5pt'],
            $rowCount >= 7  => ['9pt', '1pt'],
            $rowCount >= 5  => ['11pt', '1.5pt'],
            default         => ['16pt', '2.5pt'],
        };

        $presidentName   = \App\Models\Setting::get('union_president_name', 'المهندس/ سهيل هاشم السقا');
        $presidentTitle1 = \App\Models\Setting::get('union_president_title1', 'نقيب المقاولين الفلسطينيين');
        $presidentTitle2 = \App\Models\Setting::get('union_president_title2', 'النائب الأول لرئيس الاتحاد');

        $assets   = resource_path('certificate-assets');
        $headerImg = $assets . DIRECTORY_SEPARATOR . 'letterhead-header.jpg';
        $footerImg = $assets . DIRECTORY_SEPARATOR . 'letterhead-footer.png';
        $sealImg   = $assets . DIRECTORY_SEPARATOR . 'union-seal.jpg';

        $companyLabelEsc  = e($companyLabel);
        $addressEsc       = e($address);

        // القالب يكتب "184/غ" — لاحقة غزة. الترقيم الجديد ({n}_g) يحملها أصلاً،
        // فإلحاق "/غ" به يكرّرها ("932_g/غ")، لذا تُضاف للترقيم القديم فقط.
        $membershipNumber = e($contractor->membership_number);
        if (! str_ends_with(strtolower($contractor->membership_number ?? ''), '_g')) {
            $membershipNumber .= '/غ';
        }

        // أحجام الخطوط والألوان والمحاذاة أدناه منقولة حرفياً من قالب Word المعتمد
        // (w:sz بأنصاف النقاط ÷ 2 = pt). أي تعديل هنا يخرج الشهادة عن مطابقة الأصل.
        $html = <<<HTML
<style>
  body { font-family: calibri; direction: rtl; color: #000; font-size: 17pt; }
  .meta { text-align: right; margin: 0 0 2pt 0; font-family: calibri; font-size: 12pt; color: #D80F18; font-weight: bold; line-height: 1.25; }
  .title {
    font-family: ptboldheading; text-align: center;
    font-size: 26pt; font-weight: bold; margin: 2pt 0 10pt 0;
  }
  .line { margin: 0 0 6pt 0; line-height: 1.4; font-weight: bold; }
  .intro { font-family: timesnewroman; font-size: 21pt; font-weight: bold; }
  .company { font-family: ptboldheading; font-size: 25pt; font-weight: bold; }
  .addr { font-size: 17pt; font-weight: bold; }
  .member-no { font-size: 17pt; font-weight: bold; }
  .classified { font-size: 16pt; font-weight: bold; }
  .decision { font-size: 17pt; font-weight: bold; }
  /* القالب: 3 أعمدة × 2952 twips = 156mm إجمالاً، ملتصق باليمين (tblpXSpec=right).
     لا نستخدم width:auto — يكسر تدفّق mPDF ويدفع بقية المحتوى لصفحات إضافية. */
  table.data-table { width: 156mm; border-collapse: collapse; margin: 10pt 0 10pt auto; }
  table.data-table th, table.data-table td {
    border: 0.5pt solid #000; padding: {$tableCellPad};
    font-size: {$tableFontPt}; text-align: center; font-weight: normal; line-height: 1.2;
  }
  table.data-table th { background-color: #8C8C8C; }
  table.data-table td { font-family: arial; }
  .validity { font-size: 16pt; font-weight: bold; margin-top: 10pt; line-height: 1.4; }
  /* عرض أضيق ومتوسّط يقرّب الختم من نص التوقيع بدل تباعدهما على طرفي الصفحة */
  .sign-block { width: 74%; margin: 6pt auto 0 auto; }
  .sign-block td { vertical-align: middle; }
  .sign-text { text-align: center; font-size: 13pt; font-weight: bold; line-height: 1.35; }
  .seal-cell { text-align: center; }
</style>

<div class="meta">الرقـم: <span dir="ltr">{$serial}</span><br>التاريخ: {$issuedAt}</div>

<div class="title">شهادة عضـــــــــوية</div>

<div class="line"><span class="intro">يـــــــــشهد اتحــــــــاد المقـــــــاولين الفلــــــــسطينيين بأن</span></div>
<div class="line"><span class="company">{$companyLabelEsc}</span></div>
<div class="line addr">وعنوانــــها / {$addressEsc}</div>
<div class="line member-no">عضوا في الاتحاد تحت رقم {$membershipNumber}</div>
<div class="line">
  <span class="classified">ومصنفة فـــي المجــالات والتخصــصات التاليــة بمـوجب قـرار لـجنة التصنيف الوطنية</span>
  <span class="decision">رقم ({$decisionNumber}) بتاريخ : {$decisionDate}م</span>
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

<!-- في جدول RTL أول خلية تُرسم يميناً: الختم يميناً ونص التوقيع شماله -->
<table class="sign-block" autosize="1">
  <tr>
    <td width="50%" class="seal-cell">
      <img src="{$sealImg}" width="195" height="151">
    </td>
    <td width="50%" class="sign-text">
      {$presidentName}<br>
      {$presidentTitle1}<br>
      {$presidentTitle2}
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

        // خطوط القالب المعتمد نفسها، مشحونة من resources/certificate-assets/fonts
        // لتُطابق الشهادة الأصل بصرياً على أي سيرفر بلا خطوط نظام.
        // ملاحظة: Amiri وNoto Naskh الموجودان بنفس المجلد غير مستعملين — كلاهما يحوي
        // GPOS Lookup Type 5 Format 3 غير المدعوم في محلّل OTL بـ mPDF فيرمي FontException.
        $defaultConfig     = (new \Mpdf\Config\ConfigVariables)->getDefaults();
        $defaultFontConfig = (new \Mpdf\Config\FontVariables)->getDefaults();

        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'orientation'   => 'P',
            'margin_left'   => 15,
            'margin_right'  => 15,
            // الهامش العلوي/السفلي يبدأ بعد الترويسة/التذييل، وmargin_header/footer
            // يحدّدان بعدهما عن حافة الورقة
            // margin_header(8) + headerH هو أدنى حد؛ أقل منه يدوس المتن صورة الترويسة.
            // +2 هامش أمان لأن صندوق أول سطر يبدأ قبل حدّ الهامش بكسر نقطة.
            'margin_top'    => $headerH + 10,
            'margin_bottom' => $footerH + 8,
            'margin_header' => 8,
            'margin_footer' => 8,
            'tempDir'       => $tempDir,

            'fontDir'  => array_merge($defaultConfig['fontDir'], [$assets . DIRECTORY_SEPARATOR . 'fonts']),
            'fontdata' => $defaultFontConfig['fontdata'] + [
                'ptboldheading' => ['R' => 'PTBoldHeading.ttf', 'useOTL' => 0xFF],
                'timesnewroman' => ['R' => 'TimesNewRoman.ttf', 'B' => 'TimesNewRoman-Bold.ttf', 'useOTL' => 0xFF],
                'arial'         => ['R' => 'Arial.ttf', 'useOTL' => 0xFF],
                'calibri'       => ['R' => 'Calibri.ttf', 'B' => 'Calibri-Bold.ttf', 'useOTL' => 0xFF],
            ],
            'default_font'  => 'calibri',
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

        // قفل تعديل/نسخ المحتوى بكلمة سر مالك — بدون كلمة سر مستخدم، فالملف يُفتح ويُطبع بحرية
        $mpdf->SetProtection(['print', 'copy'], '', config('app.certificate_pdf_owner_password'));

        $path = $preview
            ? "certificates/preview/{$serial}.pdf"
            : "certificates/membership/{$serial}.pdf";

        Storage::disk('public')->put($path, $mpdf->Output('', 'S'));

        return $path;
    }
}
