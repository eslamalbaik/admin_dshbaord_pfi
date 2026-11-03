<?php

namespace App\Services;

use App\Models\CertificateRequest;
use App\Models\Contractor;
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
 *   الأصول مستخرَجة من قالب "ترويسة الاتحاد" (resources/certificate-assets).
 *   الترويسة والتذييل بعرض الورقة كاملاً (210mm، بلا هوامش) كما في قالب Word، فتُرسم
 *   بإحداثيات مطلقة لا عبر SetHTMLHeader/Footer اللذين يحصرانهما داخل الهوامش.
 *   الشعار الباهت بالخلفية علامة مائية بنفس مقاسه في القالب.
 *
 * ملاحظة: التوليد يستغرق ثوانيَ عدة، فلا يجوز استدعاؤه داخل طلب HTTP متزامن.
 */
class MembershipCertificatePdfService
{
    /** ارتفاع الترويسة والتذييل بالمليمتر — نسبة أبعاد الصورتين (1241×286 و2040×259) على عرض 210mm */
    private const HEADER_HEIGHT_MM = 48.4;
    private const FOOTER_HEIGHT_MM = 26.7;

    /**
     * @param  bool  $preview  يكتب في certificates/preview بدل مسار الشهادة الرسمي.
     *                         بدونه، أي توليد اختباري يدهس شهادة صادرة تحمل نفس الرقم.
     */
    /**
     * بيانات الشهادة كما هي مسجّلة بملف المقاول — اللي بتنطبع لما ما في overrides.
     * سجل المحددات الهامة بيقارن فيها أي بيانات مُدخلة يدوياً عند الإصدار.
     *
     * @return array{address: ?string, decision_number: ?string, decision_date: ?string}
     */
    public static function recordFields(Contractor $contractor): array
    {
        return [
            'address'         => $contractor->city ?: $contractor->governorate?->name ?: $contractor->address ?: null,
            'decision_number' => $contractor->classification_decision_number ?: null,
            'decision_date'   => $contractor->classification_decision_date?->format('d/m/Y'),
        ];
    }

    /**
     * "سارية المفعول حتى" الافتراضية على شهادة العضوية: انتهاء آخر عضوية للمقاول (31/12 من سنة العضوية)،
     * وإلا 31/12 من السنة الحالية. الإدخال اليدوي بيغلب عليها.
     */
    public static function validUntil(Contractor $contractor): \Illuminate\Support\Carbon
    {
        $last = $contractor->memberships()->whereNotNull('expires_at')->latest('expires_at')->value('expires_at');

        return $last
            ? \Illuminate\Support\Carbon::parse($last)->startOfDay()
            : now()->endOfYear()->startOfDay();
    }

    /**
     * رابط صفحة التحقق العامة — هو اللي بيحمله رمز QR على الشهادة. الرابط المختصر
     * (pcu.org.ps/verify) بيعرض نفس صفحة landing/verify-certificate، وهي باقية للروابط القديمة.
     */
    public static function verificationUrl(string $serial): string
    {
        return config('app.certificate_verify_url') . '?serial=' . urlencode($serial);
    }

    /**
     * خطوات تصغير الشهادة من الأكبر للأصغر. الحلقة بتبدأ دايماً من أكبر خط وبتنزل خطوة كل ما
     * طلعت صفحتين (كل رسم أقل من ثانية): خطوط الجدول أصغر، ثم كاحتياط أخير تصغير المتن والختم كله.
     *
     * @return list<array{font: float, pad: float, scale: float, seal: float}>
     */
    public static function layoutSteps(): array
    {
        // [خط الجدول, حشوة الخلية] بالـpt
        $table = [
            [16, 2.5], [14, 2], [12, 1.5], [11, 1.2], [10, 1], [9, 0.8], [8.5, 0.5], [8, 0.45],
            [7.5, 0.4], [6.5, 0.3], [5.5, 0.2], [5, 0.2], [4.5, 0.1], [4, 0.1],
        ];

        $steps = [];
        foreach ($table as [$font, $pad]) {
            $steps[] = ['font' => $font, 'pad' => $pad, 'scale' => 1.0, 'seal' => 1.0];
        }
        // احتياط أخير: تصغير المتن كله تدريجياً مع أصغر جدول
        foreach ([0.9, 0.8, 0.7, 0.6, 0.5] as $k) {
            $steps[] = ['font' => 4, 'pad' => 0.1, 'scale' => $k, 'seal' => $k];
        }

        return $steps;
    }

    public function generate(CertificateRequest $certRequest, array $overrides = [], bool $preview = false): string
    {
        $contractor = $certRequest->contractor;
        $membership = $contractor->activeMembership;

        // الرقم المرجعي الداخلي (أعلى الشهادة) مبني على رقم الطلب — منفصل عن الرقم التسلسلي
        $reference = 'MC-' . str_pad((string) $certRequest->id, 6, '0', STR_PAD_LEFT);
        // الرقم التسلسلي الفريد (أسفل الشهادة): يتولّد مرة واحدة ويبقى ثابتاً عند إعادة الإصدار. المعاينة ما بتحجز رقماً.
        $serial = $preview
            ? ($certRequest->serial_number ?: '----')
            : $certRequest->ensureSerialNumber();
        $verifyUrl = self::verificationUrl($serial);
        // صيغ التواريخ مطابقة للقالب: الترويسة d-m-Y، ومتن الشهادة d/m/Y
        $issuedAt = now()->format('d-m-Y');

        $defaults = self::recordFields($contractor);

        $decisionDate   = $overrides['decision_date'] ?? ($defaults['decision_date'] ?? '—');
        // اللوحة بتبعت التاريخ من حقل type=date بصيغة Y-m-d، والمتن بيطبعه d/m/Y
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $decisionDate)) {
            $decisionDate = \Illuminate\Support\Carbon::parse($decisionDate)->format('d/m/Y');
        }
        $decisionNumber = $overrides['decision_number'] ?? ($defaults['decision_number'] ?? '—');
        $validUntilDate = isset($overrides['valid_until'])
            ? \Illuminate\Support\Carbon::parse($overrides['valid_until'])
            : self::validUntil($contractor);
        $validUntil = $validUntilDate->format('d/m/Y');
        // نفس التاريخ المطبوع بيرجع للتطبيق (expiry_date) — بينحفظ مع update() الإصدار
        $certRequest->valid_until = $validUntilDate->toDateString();

        $address = $overrides['address'] ?? ($defaults['address'] ?? '—');

        // القالب الأصلي لا يكتب "شركة/" ثابتة — نتجنّب تكرارها لو كان الاسم حاملاً لها
        $companyLabel = str_contains($contractor->name, 'شركة')
            ? $contractor->name
            : "شركة/ {$contractor->name}";

        $specialtiesRows = '';
        foreach (ContractorLookups::buildFieldsTree($contractor->specialties) as $field) {
            foreach ($field['specializations'] as $spec) {
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

        // الشهادة يجب أن تبقى صفحة واحدة مهما كان عدد التخصصات. كتلة التوقيع (الختم + الأسماء)
        // لا تقبل التقسيم في mPDF، فإن لم يتبقَّ لها ارتفاع كافٍ قفزت لصفحة ثانية بالكامل.
        // لذلك: نبدأ بأكبر خط، نولّد ونعدّ الصفحات، وإن زادت عن صفحة نجرّب الخطوة الأصغر التالية —
        // فالنتيجة صفحة واحدة دائماً بأكبر خط بيوسع. (الختم والتوقيع صاروا بالتذييل، فما بيقفزوا.)
        $layoutSteps = self::layoutSteps();


        $presidentName   = \App\Models\Setting::get('union_president_name', 'المهندس/ سهيل هاشم السقا');
        $presidentTitle1 = \App\Models\Setting::get('union_president_title1', 'نقيب المقاولين الفلسطينيين');
        $presidentTitle2 = \App\Models\Setting::get('union_president_title2', 'النائب الأول لرئيس الاتحاد');

        $assets       = resource_path('certificate-assets');
        $headerImg    = $assets . DIRECTORY_SEPARATOR . 'letterhead-header.png';
        $footerImg    = $assets . DIRECTORY_SEPARATOR . 'letterhead-footer.png';
        $watermarkImg = $assets . DIRECTORY_SEPARATOR . 'letterhead-watermark.png';
        $sealImg      = $assets . DIRECTORY_SEPARATOR . 'union-seal.png';
        $headerH      = self::HEADER_HEIGHT_MM;
        $footerH      = self::FOOTER_HEIGHT_MM;

        $tempDir = storage_path('app/mpdf-tmp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }
        // QR التحقق كملف SVG مؤقت (يُحذف بعد التوليد)؛ الرابط المختصر مطبوع تحته نصاً
        $verifyPageUrl = e(config('app.certificate_verify_url'));
        $qrFile = $tempDir . DIRECTORY_SEPARATOR . "qr-{$reference}-" . uniqid() . '.svg';
        file_put_contents($qrFile, \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(300)->margin(0)
            ->errorCorrection('M')->color(0, 0, 0)->generate($verifyUrl));
        $serialEsc = e($serial);

        $companyLabelEsc  = e($companyLabel);
        $addressEsc       = e($address);

        // القالب يكتب "184/غ" — لاحقة غزة. الترقيم الجديد ({n}_g) يحملها أصلاً،
        // فإلحاق "/غ" به يكرّرها ("932_g/غ")، لذا تُضاف للترقيم القديم فقط.
        $membershipNumber = e($contractor->membership_number);
        if (! str_ends_with(strtolower($contractor->membership_number ?? ''), '_g')) {
            $membershipNumber .= '/غ';
        } else {
            // "1004_g" جوّا سطر عربي بينقلب لـ "g_1004" بدون عزل الاتجاه، زي الرقم التسلسلي فوق
            $membershipNumber = '<span dir="ltr">' . $membershipNumber . '</span>';
        }

        foreach ($layoutSteps as $stepIndex => $step) {
            [$tableFontPt, $tableCellPad] = [$step['font'] . 'pt', $step['pad'] . 'pt'];
            $k   = $step['scale'];
            $pt  = fn (float $v) => round($v * $k, 2) . 'pt';
            $f   = [
                'body' => $pt(17), 'title' => $pt(26), 'intro' => $pt(21), 'company' => $pt(25),
                'line' => $pt(17), 'classified' => $pt(16), 'validity' => $pt(16), 'sign' => $pt(13),
                'gapS' => $pt(6), 'gapM' => $pt(10),
            ];
            // سطر التوقيع: الأعمدة الثلاثة بارتفاع متقارب (~34mm بالحجم الطبيعي) — الختم 152×128px،
            // الـQR ‏85px + ثلاثة أسطر، والاسم وصفتاه + فراغ التوقيع اليدوي
            $sealW   = (int) round(152 * $step['seal']);
            $sealH   = (int) round(128 * $step['seal']);
            $qrPx    = (int) round(85 * $step['seal']);
            $signGap = round(16 * $step['seal'], 1) . 'mm';

            // أحجام الخطوط والألوان والمحاذاة أدناه منقولة حرفياً من قالب Word المعتمد
            // (w:sz بأنصاف النقاط ÷ 2 = pt). أي تعديل هنا يخرج الشهادة عن مطابقة الأصل.
            $html = <<<HTML
<style>
  body { font-family: calibri; direction: rtl; color: #000; font-size: {$f['body']}; }
  /* العنوان واسم الشركة بكحلي هوية الاتحاد — نفس لون "اتحاد المقاولين الفلسطينيين" بالترويسة */
  .title {
    font-family: ptboldheading; text-align: center; color: #2E3192;
    font-size: {$f['title']}; font-weight: bold; margin: 2pt 0 {$f['gapM']} 0;
  }
  .line { margin: 0 0 {$f['gapS']} 0; line-height: 1.4; font-weight: bold; }
  .intro { font-family: timesnewroman; font-size: {$f['intro']}; font-weight: bold; }
  .company { font-family: ptboldheading; font-size: {$f['company']}; font-weight: bold; color: #2E3192; }
  .addr { font-size: {$f['line']}; font-weight: bold; }
  .member-no { font-size: {$f['line']}; font-weight: bold; }
  .classified { font-size: {$f['classified']}; font-weight: bold; }
  .decision { font-size: {$f['line']}; font-weight: bold; }
  /* الجدول بعرض المتن كاملاً، فطرفاه محاذيان للنص يميناً ويساراً.
     لا نستخدم width:auto — يكسر تدفّق mPDF ويدفع بقية المحتوى لصفحات إضافية. */
  table.data-table { width: 100%; border-collapse: collapse; margin: {$f['gapM']} 0; }
  table.data-table th, table.data-table td {
    border: 0.5pt solid #000; padding: {$tableCellPad};
    font-size: {$tableFontPt}; text-align: center; font-weight: normal; line-height: 1.2;
  }
  table.data-table th { background-color: #8C8C8C; }
  table.data-table td { font-family: arial; }
  .validity { font-size: {$f['validity']}; font-weight: bold; margin-top: {$f['gapM']}; line-height: 1.4; }
  /* الأعمدة الثلاثة تبدأ من خط علوي واحد */
  .sign-block { width: 100%; margin: {$f['gapS']} 0 0 0; }
  .sign-block td { vertical-align: top; }
  .seal-cell { text-align: center; }
  .sign-text { text-align: center; font-weight: bold; line-height: 1.35; }
  .sign-name { color: #2E3192; font-size: {$pt(15)}; }
  .sign-title1 { font-size: {$pt(14)}; }
  .sign-title2 { font-size: {$pt(11)}; }
  .sign-space { height: {$signGap}; }
  /* خانة التحقق يمين الصفحة: QR والرقم التسلسلي ورابط التحقق، بالأسود وبلا إطارات */
  .verify-cell { text-align: center; color: #000; font-size: {$pt(8)}; line-height: 1.3; }
  .verify-ref { font-size: {$pt(10)}; font-weight: bold; }
</style>

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

<!-- في جدول RTL أول خلية تُرسم يميناً: التحقق يميناً، الختم بالوسط، والتوقيع يساراً.
     الرقم التسلسلي بـspan LTR معزول فالنسخ من الـPDF بيطلع بالترتيب الصحيح. -->
<table class="sign-block" autosize="1">
  <tr>
    <td width="30%" class="verify-cell">
      <img src="{$qrFile}" width="{$qrPx}" height="{$qrPx}"><br>
      الرقم التسلسلي: <span class="verify-ref" dir="ltr">{$serialEsc}</span><br>
      امسح الرمز للتحقق من صحة الشهادة<br>
      <span dir="ltr">{$verifyPageUrl}</span>
    </td>
    <td width="32%" class="seal-cell">
      <img src="{$sealImg}" width="{$sealW}" height="{$sealH}">
    </td>
    <td width="38%" class="sign-text">
      <span class="sign-name">{$presidentName}</span><br>
      <span class="sign-title1">{$presidentTitle1}</span><br>
      <span class="sign-title2">{$presidentTitle2}</span>
      <div class="sign-space"></div>
    </td>
  </tr>
</table>

HTML;

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
                // الترويسة والتذييل ملتصقان بحافة الورقة؛ الهامشان يحجزان مساحتهما للمتن
                'margin_top'    => $headerH + 4,
                'margin_bottom' => $footerH + 2,
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

            // الشعار الباهت بوسط الصفحة خلف المتن، بمقاسه في قالب Word (123×100mm).
            // الصورة باهتة أصلاً، فلا شفافية إضافية.
            $mpdf->SetWatermarkImage($watermarkImg, 1, [123, 100], 'P');
            $mpdf->showWatermarkImage = true;
            $mpdf->watermarkImgBehind = true;

            $mpdf->WriteHTML($html);

            // عدد الصفحات بعد الرسم — صفحة واحدة = خلصنا. آخر خطوة بتنقبل كما هي.
            if (count($mpdf->pages) <= 1 || $stepIndex === array_key_last($layoutSteps)) {
                break;
            }
        }

        // الترويسة والتذييل بعرض الورقة كاملاً تُرسم بإحداثيات مطلقة بعد المتن — الهوامش تبقيه
        // بعيداً عنهما. SetHTMLHeader/Footer يحصرانهما داخل الهوامش، وAddPage قبل WriteHTML
        // يدفع المتن لصفحة ثانية. التذييل تحت الهامش السفلي، فبدون إيقاف القفز التلقائي
        // يفتح صفحة ثانية فارغة. constrain=false: بدونه mPDF يصغّر الصورة لعرض المتن (180mm).
        $mpdf->SetAutoPageBreak(false);
        $mpdf->Image($headerImg, 0, 0, 210, $headerH, '', '', true, false);
        $mpdf->Image($footerImg, 0, 297 - $footerH, 210, $footerH, '', '', true, false);

        // الترويسة تطبع "الرقم: ....." و"التاريخ: ....." بنفسها؛ القيمة تُكتب فوق النقاط
        // بخلفية بيضاء تغطيها. المواضع مقيسة من بكسلات النقاط بصورة الترويسة (× 210/1241):
        // نقاط الرقم 27–44.5mm، نقاط التاريخ 155.7–173mm، وكلاهما على ارتفاع 39.6–42mm.
        $mpdf->SetFillColor(255, 255, 255);
        $mpdf->SetTextColor(0, 32, 96);
        $mpdf->SetFont('calibri', 'B', 10);
        $mpdf->SetXY(26.5, 38);
        $mpdf->Cell(18.5, 5.5, $reference, 0, 0, 'C', true);
        $mpdf->SetXY(155, 38);
        $mpdf->Cell(18.5, 5.5, $issuedAt, 0, 0, 'C', true);

        // قفل تعديل/نسخ المحتوى بكلمة سر مالك — بدون كلمة سر مستخدم، فالملف يُفتح ويُطبع بحرية
        $mpdf->SetProtection(['print', 'copy'], '', config('app.certificate_pdf_owner_password'));

        $path = $preview
            ? "certificates/preview/{$reference}.pdf"
            : "certificates/membership/{$reference}.pdf";

        Storage::disk('public')->put($path, $mpdf->Output('', 'S'));
        @unlink($qrFile);

        return $path;
    }
}
