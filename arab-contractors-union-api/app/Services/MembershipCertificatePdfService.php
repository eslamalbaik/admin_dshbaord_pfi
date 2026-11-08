<?php

namespace App\Services;

use App\Models\CertificateRequest;
use App\Models\Contractor;
use App\Support\ContractorLookups;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * توليد PDF شهادة العضوية (REQ-02) بمحرّك mPDF — بديل مسار LibreOffice.
 *
 * لماذا mPDF وليس تحويل DOCX عبر LibreOffice:
 *   سيرفر cPanel الإنتاجي لا يحوي soffice ولا تُمكن إضافته بلا صلاحيات root،
 *   فمسار DOCX→PDF يفشل هناك حتماً. mPDF حزمة PHP خالصة داخل vendor/.
 *
 * التصميم (2026-11-08):
 *   الخلفية membership-certificate-template.pdf هي ملف المصمم ("شهادة عضوية 3"،
 *   Illustrator) نفسه بعد حذف كل ما يتغيّر من شهادة لأخرى. يبقى فيها: الترويسة
 *   والتذييل والعلامة المائية، عنوان "شهادة عضوية" وزخرفته، سطر "يشهد اتحاد…"،
 *   شريط رأس الجدول، الختم وخط التوقيع. الباقي يُكتب فوقها بإحداثيات مقيسة من
 *   الملف الأصلي (pt × 25.4/72 = mm).
 *
 *   خطوط التصميم (DIN Next LT Arabic وzain-mob) تجارية وغير متوفرة لدينا، فاستُعمل
 *   أقرب بديل مجاني: IBM Plex Sans Arabic مكان DIN، وZain (Google Fonts) مكان zain.
 *   لو توفرت الخطوط الأصلية يكفي تبديل الملفات في تعريف fontdata أدناه.
 *
 * ملاحظة: التوليد يستغرق ثوانيَ عدة، فلا يجوز استدعاؤه داخل طلب HTTP متزامن.
 */
class MembershipCertificatePdfService
{
    private const NAVY = '#2D398D';
    private const INK  = '#231F20';

    /**
     * جدول التخصصات: أعلاه ثابت تحت شريط الرأس المرسوم بالقالب، وارتفاع الصف بالتصميم
     * يتسع لثمانية صفوف قبل منطقة التوقيع. بعد الثمانية يُصغَّر الصف والخط معاً.
     */
    private const TABLE_TOP_MM    = 130.4;
    private const ROW_HEIGHT_PT   = 31.46;
    private const TABLE_BODY_PT   = 262;

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
    public static function validUntil(Contractor $contractor): Carbon
    {
        $last = $contractor->memberships()->whereNotNull('expires_at')->latest('expires_at')->value('expires_at');

        return $last
            ? Carbon::parse($last)->startOfDay()
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
     * @param  bool  $preview  يكتب في certificates/preview بدل مسار الشهادة الرسمي.
     *                         بدونه، أي توليد اختباري يدهس شهادة صادرة تحمل نفس الرقم.
     */
    public function generate(CertificateRequest $certRequest, array $overrides = [], bool $preview = false): string
    {
        $contractor = $certRequest->contractor;
        $defaults   = self::recordFields($contractor);

        $rows = [];
        foreach (ContractorLookups::buildFieldsTree($contractor->specialties) as $field) {
            foreach ($field['specializations'] as $spec) {
                // التصميم يكتب الدرجة بصيغتها المختصرة ("أولى أ") لا بمسمّى العرض في اللوحة
                $rows[] = [$field['field_name'], $spec['spec_name'], $spec['grade'] ?? $spec['grade_label'] ?? '—'];
            }
        }

        // الرقم المرجعي الداخلي (اسم الملف) مبني على رقم الطلب — منفصل عن الرقم التسلسلي
        $reference = 'MC-' . str_pad((string) $certRequest->id, 6, '0', STR_PAD_LEFT);
        // الرقم التسلسلي الفريد (تحت الـQR): يتولّد مرة واحدة ويبقى ثابتاً عند إعادة الإصدار. المعاينة ما بتحجز رقماً.
        $serial = $preview
            ? ($certRequest->serial_number ?: '----')
            : $certRequest->ensureSerialNumber();

        $validUntil = isset($overrides['valid_until'])
            ? Carbon::parse($overrides['valid_until'])
            : self::validUntil($contractor);
        // نفس التاريخ المطبوع بيرجع للتطبيق (expiry_date) — بينحفظ مع update() الإصدار
        $certRequest->valid_until = $validUntil->toDateString();

        $pdf = $this->render([
            'serial'           => $serial,
            'issued_at'        => now(),
            'company'          => $contractor->name,
            'membership_number' => $contractor->membership_number,
            'address'          => $overrides['address'] ?? ($defaults['address'] ?? '—'),
            'decision_number'  => $overrides['decision_number'] ?? ($defaults['decision_number'] ?? '—'),
            'decision_date'    => $overrides['decision_date'] ?? $defaults['decision_date'],
            'valid_until'      => $validUntil,
            'rows'             => $rows,
            'verify_url'       => self::verificationUrl($serial),
            'president_name'   => \App\Models\Setting::get('union_president_name', 'المهندس/ سهيل هاشم السقا'),
            'president_title1' => \App\Models\Setting::get('union_president_title1', 'نقيب المقاولين الفلسطينيين'),
            'president_title2' => \App\Models\Setting::get('union_president_title2', 'النائب الأول لرئيس الاتحاد'),
        ]);

        $path = $preview
            ? "certificates/preview/{$reference}.pdf"
            : "certificates/membership/{$reference}.pdf";

        Storage::disk('public')->put($path, $pdf);

        return $path;
    }

    /**
     * يرسم الشهادة ويرجع بايتات الـPDF. مفصول عن generate() ليُختبر بلا قاعدة بيانات.
     *
     * @param  array{serial: string, issued_at: \DateTimeInterface, company: string, membership_number: ?string,
     *               address: string, decision_number: string, decision_date: mixed, valid_until: mixed,
     *               rows: list<array{0: string, 1: string, 2: string}>, verify_url: string,
     *               president_name: string, president_title1: string, president_title2: string}  $d
     */
    public function render(array $d): string
    {
        $assets  = resource_path('certificate-assets');
        $tempDir = storage_path('app/mpdf-tmp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $defaultConfig     = (new \Mpdf\Config\ConfigVariables)->getDefaults();
        $defaultFontConfig = (new \Mpdf\Config\FontVariables)->getDefaults();

        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'orientation'   => 'P',
            // حدود الجدول بالتصميم: من 49pt إلى 543pt عن يسار الورقة — mPDF يعكس الهامشين في RTL، فـmargin_left هو الهامش الأيمن فعلياً
            'margin_left'   => 18.34,
            'margin_right'  => 17.17,
            'margin_top'    => self::TABLE_TOP_MM,
            'margin_bottom' => 20,
            'tempDir'       => $tempDir,

            'fontDir'  => array_merge($defaultConfig['fontDir'], [$assets . DIRECTORY_SEPARATOR . 'fonts']),
            'fontdata' => $defaultFontConfig['fontdata'] + [
                // بديل DIN Next LT Arabic Regular/Bold
                'plex'       => ['R' => 'IBMPlexSansArabic-Regular.ttf', 'B' => 'IBMPlexSansArabic-Bold.ttf', 'useOTL' => 0xFF],
                // بديل DIN Next LT Arabic Medium
                'plexmedium' => ['R' => 'IBMPlexSansArabic-Medium.ttf', 'B' => 'IBMPlexSansArabic-Bold.ttf', 'useOTL' => 0xFF],
                // بديل zain-mob (اسم الشركة)
                'zainblack'  => ['R' => 'Zain-Black.ttf', 'useOTL' => 0xFF],
            ],
            'default_font'  => 'plex',
        ]);

        $mpdf->SetDirectionality('rtl');
        // autoLangToFont يبدّل الخط تلقائياً حسب السكربت المكتشَف فيتجاوز الخط المحدَّد في CSS
        $mpdf->autoScriptToLang = false;
        $mpdf->autoLangToFont   = false;
        $mpdf->SetDocTemplate($assets . DIRECTORY_SEPARATOR . 'membership-certificate-template.pdf', true);

        $e = fn ($v) => e((string) $v);

        // ── جدول التخصصات + سطر الصلاحية: بالتدفق الطبيعي من أعلى الهامش ──────
        $count  = max(count($d['rows']), 1);
        $rowPt  = min(self::ROW_HEIGHT_PT, self::TABLE_BODY_PT / $count);
        $cellPt = round(min(16, $rowPt * 0.51), 1);
        $gradePt = round(min(15, $rowPt * 0.48), 1);

        $body = '';
        foreach ($d['rows'] as $i => [$fieldName, $specName, $grade]) {
            // التصميم يظلّل الصفوف الزوجية بلون بنفسجي فاتح شفاف (27%) فوق العلامة المائية
            $bg = $i % 2 ? 'background-color: rgba(201, 204, 225, 0.27);' : '';
            $body .= "<tr style=\"{$bg}\">"
                . '<td class="c-field">' . $e($fieldName) . '</td>'
                . '<td class="c-spec">' . $e($specName) . '</td>'
                . '<td class="c-grade">' . $e($grade) . '</td>'
                . '</tr>';
        }
        if ($body === '') {
            $body = '<tr><td colspan="3" style="text-align:center;color:#888;">لا يوجد تخصصات مسجّلة</td></tr>';
        }

        $validUntil = $this->ymd($d['valid_until']);
        $ink  = self::INK;

        $mpdf->WriteHTML(<<<HTML
<style>
  body { font-family: plex; direction: rtl; color: {$ink}; }
  table.spec { width: 100%; border-collapse: collapse; }
  table.spec td {
    height: {$rowPt}pt; padding: 0 18pt 0 4pt; vertical-align: middle;
    font-family: plexmedium; font-size: {$cellPt}pt; text-align: right;
    border: 0.75pt solid #D9DADA;
  }
  /* أعمدة التصميم: المجال 165pt، الاختصاص 229pt، الدرجة 100pt */
  table.spec td.c-field { width: 33.4%; }
  table.spec td.c-spec { width: 46.4%; }
  table.spec td.c-grade { width: 20.2%; font-size: {$gradePt}pt; padding-right: 33pt; }
  .validity { text-align: center; font-size: 13pt; margin-top: 6pt; }
</style>
<table class="spec" autosize="1">{$body}</table>
<div class="validity">هذه الشهادة سارية المفعول حتى تاريخ <b>{$validUntil}م</b> وبعدها تعتبر لاغية.</div>
HTML);

        // ── العناصر الثابتة الموضع فوق الجدول وتحته ───────────────────────────
        $mpdf->SetAutoPageBreak(false);

        $navy = self::NAVY;

        // التاريخ بجانب "التاريخ:" المطبوعة بالترويسة
        $mpdf->WriteFixedPosHTML(
            "<div style=\"font-family: plexmedium; font-size: 11pt; color: {$navy}; text-align: right;\" dir=\"ltr\">"
            . $this->ymd($d['issued_at']) . '</div>',
            150, 36.4, 40.3, 6
        );

        // اسم الشركة — overflow=auto يصغّر الخط لو الاسم أطول من سطر
        $company = str_contains($d['company'], 'شركة') ? $d['company'] : "شركة/ {$d['company']}";
        $mpdf->WriteFixedPosHTML(
            "<div style=\"font-family: zainblack; font-size: 31pt; color: {$navy}; text-align: center;\">"
            . $e($company) . '</div>',
            17.3, 85.5, 174.3, 15, 'auto'
        );

        // "184/غ" — لاحقة غزة. الترقيم الجديد ({n}_g) يحملها أصلاً، فتُضاف للقديم فقط
        $number = (string) $d['membership_number'];
        $number = str_ends_with(strtolower($number), '_g')
            ? '<span dir="ltr">' . $e($number) . '</span>'
            : $e($number) . '/غ';
        $mpdf->WriteFixedPosHTML(
            "<div style=\"font-family: plexmedium; font-size: 14.1pt; text-align: center;\">"
            . "عضـواً في الاتحاد تحت رقم <b>{$number}</b>"
            . "<span style=\"color: #9E9E9E;\">&nbsp; | &nbsp;</span>"
            . 'وعنوانها: <b>' . $e($d['address']) . '</b></div>',
            17.3, 100.2, 174.3, 9, 'auto'
        );

        $mpdf->WriteFixedPosHTML(
            '<div style="font-size: 11.3pt; text-align: center;">'
            . 'ومصنفة في المجالات والاختصاصات التالية بموجب قرار لجنة التصنيف الوطنية رقم '
            . '(<span style="font-family: plexmedium;">' . $e($d['decision_number']) . '</span>)، بتاريخ: '
            . '<span style="font-family: plexmedium;">' . $this->ymd($d['decision_date']) . 'م</span></div>',
            15, 110.2, 180, 8, 'auto'
        );

        // التوقيع فوق الخط المرسوم بالقالب (200–394pt)
        $mpdf->WriteFixedPosHTML(
            '<div style="text-align: center; line-height: 1.55;">'
            . "<div style=\"font-family: plexmedium; font-size: 13.2pt; color: {$navy};\">" . $e($d['president_name']) . '</div>'
            . '<div style="font-size: 10.7pt;">' . $e($d['president_title1']) . '</div>'
            . '<div style="font-size: 10.4pt;">' . $e($d['president_title2']) . '</div>'
            . '</div>',
            65, 252.8, 79.5, 24, 'auto'
        );

        // QR التحقق — من simple-qrcode المثبّتة أصلاً، لا <barcode> الخاص بـmPDF
        // الذي يتطلّب حزمة mpdf/qrcode غير المثبّتة. ملف SVG مؤقت يُحذف بعد التوليد.
        $qrFile = $tempDir . DIRECTORY_SEPARATOR . 'qr-' . $d['serial'] . '-' . uniqid() . '.svg';
        file_put_contents($qrFile, QrCode::format('svg')->size(300)->margin(0)->errorCorrection('M')
            ->color(0, 0, 0)->generate($d['verify_url']));
        $mpdf->Image($qrFile, 20.3, 239.4, 22, 22, 'svg', '', true, false);
        $mpdf->WriteFixedPosHTML(
            '<div style="text-align: center; line-height: 1.2;">'
            . '<div style="font-size: 9.5pt;" dir="ltr">' . $e($d['serial']) . '</div>'
            . '<div style="font-size: 7pt;">الرقم التسلسلي للشهادة</div>'
            . '<div style="font-size: 7pt;">امسح الرمز للتحقق من صحة الشهادة</div>'
            // ليس بالتصميم، لكنه الطريق الوحيد للتحقق بالرقم التسلسلي بلا ماسح QR
            . '<div style="font-size: 7pt;" dir="ltr">' . $e(config('app.certificate_verify_url')) . '</div>'
            . '</div>',
            11.3, 261.8, 40, 15
        );

        // قفل تعديل/نسخ المحتوى بكلمة سر مالك — بدون كلمة سر مستخدم، فالملف يُفتح ويُطبع بحرية
        $mpdf->SetProtection(['print', 'copy'], '', config('app.certificate_pdf_owner_password'));

        $output = $mpdf->Output('', 'S');
        @unlink($qrFile);

        return $output;
    }

    /** التصميم يكتب التواريخ Y/m/d. المدخلات: Carbon، أو d/m/Y من recordFields، أو Y-m-d من حقل date باللوحة */
    private function ymd(mixed $date): string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y/m/d');
        }
        if (! is_string($date) || trim($date) === '' || $date === '—') {
            return '—';
        }
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', trim($date), $m)) {
            return sprintf('%04d/%02d/%02d', $m[3], $m[2], $m[1]);
        }

        try {
            return Carbon::parse($date)->format('Y/m/d');
        } catch (\Throwable) {
            return e($date);
        }
    }
}
