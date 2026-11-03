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
    private const FOOTER_HEIGHT_MM = 24; // صورة التذييل 2481×315 على 180mm ≈ 23mm

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

    /** رابط صفحة التحقق العامة (بالموقع العام) — هو اللي بيحمله رمز QR على الشهادة */
    public static function verificationUrl(string $serial): string
    {
        return rtrim((string) config('app.frontend_url'), '/') . '/landing/verify-certificate?serial=' . urlencode($serial);
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
            // حجم الختم الطبيعي (170×132px ≈ 45×35mm)
            $sealW = (int) round(170 * $step['seal']);
            $sealH = (int) round(132 * $step['seal']);
            // ارتفاع كتلة الختم والتوقيع المحجوز فوق التذييل (px→mm على 96dpi + 2mm هامش)
            $signMm = (int) ceil($sealH * 25.4 / 96) + 3;

            // أحجام الخطوط والألوان والمحاذاة أدناه منقولة حرفياً من قالب Word المعتمد
            // (w:sz بأنصاف النقاط ÷ 2 = pt). أي تعديل هنا يخرج الشهادة عن مطابقة الأصل.
            $html = <<<HTML
<style>
  body { font-family: calibri; direction: rtl; color: #000; font-size: {$f['body']}; }
  .meta { margin: 0 0 2pt 0; font-family: calibri; }
  .meta td { padding: 0; line-height: 1.3; white-space: nowrap; }
  /* "الرقم:" و"التاريخ:" أحمر 10، والقيمة مقابلهما أزرق غامق سميك 11 */
  .meta-label { color: #D80F18; font-size: 10pt; }
  .meta-value { color: #002060; font-size: 11pt; font-weight: bold; }
  .title {
    font-family: ptboldheading; text-align: center;
    font-size: {$f['title']}; font-weight: bold; margin: 2pt 0 {$f['gapM']} 0;
  }
  .line { margin: 0 0 {$f['gapS']} 0; line-height: 1.4; font-weight: bold; }
  .intro { font-family: timesnewroman; font-size: {$f['intro']}; font-weight: bold; }
  .company { font-family: ptboldheading; font-size: {$f['company']}; font-weight: bold; }
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
</style>

<!-- التاريخ والرقم بنفس السطر: بجدول RTL أول خلية يميناً، فالتاريخ يميناً والرقم ع يساره -->
<table class="meta"><tr>
  <td><span class="meta-label">التاريخ:</span> <span class="meta-value">{$issuedAt}</span></td>
  <td style="padding-right:22pt;"><span class="meta-label">الرقـم:</span> <span class="meta-value" dir="ltr">{$reference}</span></td>
</tr></table>

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
                // الشريط (ختم + توقيع + QR) بارتفاع الختم، فوق صورة التذييل
                'margin_bottom' => $footerH + $signMm + 8,
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
            $qrDataUri = 'data:image/svg+xml;base64,' . base64_encode(
                \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(200)->margin(0)->errorCorrection('M')->generate($verifyUrl)
            );

            // أسفل الشهادة: الرقم التسلسلي ورمز QR يوجّه لصفحة التحقق، ثم صورة التذييل الرسمي
            // أسفل الصفحة فوق التذييل: الختم والتوقيع (مكانهما بالقالب الأصلي، يميناً) بنفس الشريط مع
            // رمز QR والرقم التسلسلي (يساراً) — فما بيتأثروا بطول جدول التخصصات ولا بيسرقوا سطر زيادة.
            // الجدول LTR: أول خلية يساراً. الرقم بخلية LTR مستقلة فالنسخ من الـPDF بيطلع بالترتيب الصحيح.
            $mpdf->SetHTMLFooter(
                '<table dir="ltr" width="100%" style="margin-bottom:2mm;direction:ltr;border-collapse:collapse;"><tr>'
                . '<td style="width:13mm;text-align:left;vertical-align:middle;"><img src="' . $qrDataUri . '" width="42" height="42"></td>'
                . '<td style="width:52mm;vertical-align:middle;">'
                .   '<table dir="ltr" width="100%" style="border-collapse:collapse;"><tr><td style="direction:ltr;text-align:left;font-family:calibri;font-size:11pt;color:#002060;">'
                .   '<b>' . e($serial) . '</b></td></tr>'
                .   '<tr><td dir="rtl" style="text-align:left;font-family:calibri;font-size:7pt;color:#444;">'
                .   '<span style="color:#D80F18;font-size:8pt;">الرقم التسلسلي للشهادة</span><br>'
                .   'امسح الرمز للتحقق من صحة الشهادة'
                .   '</td></tr></table>'
                . '</td>'
                . '<td dir="rtl" style="text-align:center;vertical-align:middle;font-family:calibri;font-weight:bold;font-size:' . $f['sign'] . ';line-height:1.35;">'
                . e($presidentName) . '<br>' . e($presidentTitle1) . '<br>' . e($presidentTitle2)
                . '</td>'
                . '<td style="width:' . ((int) ceil($sealW * 25.4 / 96) + 2) . 'mm;text-align:right;vertical-align:middle;">'
                . '<img src="' . $sealImg . '" width="' . $sealW . '" height="' . $sealH . '"></td>'
                . '</tr></table>'
                . '<img src="' . $footerImg . '" style="width:100%;">'
            );


            $mpdf->WriteHTML($html);

            // عدد الصفحات بعد الرسم — صفحة واحدة = خلصنا. آخر خطوة بتنقبل كما هي.
            if (count($mpdf->pages) <= 1 || $stepIndex === array_key_last($layoutSteps)) {
                break;
            }
        }

        // قفل تعديل/نسخ المحتوى بكلمة سر مالك — بدون كلمة سر مستخدم، فالملف يُفتح ويُطبع بحرية
        $mpdf->SetProtection(['print', 'copy'], '', config('app.certificate_pdf_owner_password'));

        $path = $preview
            ? "certificates/preview/{$reference}.pdf"
            : "certificates/membership/{$reference}.pdf";

        Storage::disk('public')->put($path, $mpdf->Output('', 'S'));

        return $path;
    }
}
