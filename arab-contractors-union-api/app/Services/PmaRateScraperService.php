<?php

namespace App\Services;

use Spatie\Browsershot\Browsershot;

/**
 * جلب أسعار الصرف الرسمية من أداة سلطة النقد الفلسطينية (PMA) — REQ-17.
 * العملة الأساس ILS (نفس تسعير الاتحاد محلياً)، والمخرَج: كم JOD وكم USD يعادل 1 ILS.
 *
 * ملاحظة هامة: أداة التصدير (wcur.pma.ps/ar/webtools/currency/export) محمية بحقل
 * CSRF عشوائي الاسم/القيمة يتغيّر كل تحميل صفحة (لا يوجد API عام موثّق). التحقق
 * الميداني (تصفح فعلي + إرسال النموذج) أظهر أن الخدمة ترجع HTTP 503 حالياً بشكل
 * دائم — حتى من متصفح حقيقي — أي أن العطل من طرف سلطة النقد نفسها وليس حجب آلي.
 * لذلك: تنسيق الرد الناجح (JSON/CSV/Excel) غير مؤكَّد ولا يمكن اختباره فعلياً الآن.
 * الكود هنا يحاول فعلياً (سيعمل تلقائياً متى عادت الخدمة)، ويتعامل مع JSON/CSV/نص
 * بأمان، ويرفض أي شكل رد غير معروف (مثل ملف Excel ثنائي) صراحة بدل تخمين قراءته —
 * أي فشل هنا يذهب لمسار fallback+تنبيه المطوّر بأمر rates:fetch-pma.
 */
class PmaRateScraperService
{
    private const EXPORT_URL = 'https://wcur.pma.ps/ar/webtools/currency/export';

    /** حد معقول لسعر الدولار مقابل الدينار — الدينار مربوط بالدولار رسمياً (~0.708-0.709) منذ 1995 */
    private const USD_JOD_SANITY_MIN = 0.65;
    private const USD_JOD_SANITY_MAX = 0.75;

    /** @return array{ils_to_jod: float, usd_to_jod: float, date: string} — جاهزة للتخزين مباشرة */
    public function fetch(): array
    {
        $today = now()->toDateString();
        $raw   = $this->postExportForm($today);

        $result = json_decode($raw, true);
        if (! is_array($result)) {
            throw new \RuntimeException('pma_scrape_failed: تعذّر تنفيذ سكربت الجلب داخل المتصفح.');
        }

        if (($result['status'] ?? 0) !== 200) {
            throw new \RuntimeException('pma_scrape_failed: HTTP ' . ($result['status'] ?? 'unknown'));
        }

        if (empty($result['text'])) {
            throw new \RuntimeException(
                'pma_scrape_unrecognized_format: نوع محتوى غير نصي (' . ($result['contentType'] ?? 'unknown')
                . ') — على الأغلب ملف Excel ثنائي، يحتاج تنفيذ قارئ مخصص بعد رصد نموذج رد حقيقي.',
            );
        }

        // rates['JOD']/['USD'] هنا = "1 ILS = كم JOD/USD" (نفس صيغة مواصفة REQ-17 الأصلية، الأساس ILS)
        $rates = $this->parseRates($result['text'], $today);

        $ilsToJod = $rates['JOD'];
        $usdToJod = $rates['JOD'] / $rates['USD']; // كلاهما نسبةً لـILS، فالقسمة تلغيها وتبقي JOD/USD

        if ($usdToJod < self::USD_JOD_SANITY_MIN || $usdToJod > self::USD_JOD_SANITY_MAX) {
            throw new \RuntimeException(
                "pma_scrape_sanity_check_failed: USD→JOD المحسوب ({$usdToJod}) خارج نطاق ربط الدينار بالدولار المتوقع.",
            );
        }

        return ['ils_to_jod' => round($ilsToJod, 6), 'usd_to_jod' => round($usdToJod, 6), 'date' => $rates['date']];
    }

    /**
     * يشغّل صفحة أداة التصدير بمتصفح حقيقي (Chromium عبر Browsershot)، يقرأ حقل الحماية
     * (CSRF) من الـDOM، ثم يرسل النموذج عبر fetch() من داخل سياق الصفحة نفسها (نفس الجلسة/الكوكيز).
     */
    private function postExportForm(string $date): string
    {
        // تحقق سريع: هل node_modules و puppeteer مثبتة؟ إذا لا، توقّف هنا قبل محاولة بطيئة وفاشلة
        $puppeteerPath = base_path('node_modules/puppeteer');
        if (! is_dir($puppeteerPath)) {
            throw new \RuntimeException(
                'pma_scrape_missing_puppeteer: Puppeteer لم يثبّت (npm install)، تجاوز محاولة Browsershot.',
            );
        }

        $js = <<<JS
        async () => {
            const hidden = document.querySelector('input[type="hidden"]');
            if (!hidden) { return JSON.stringify({status: 0, error: 'csrf_field_not_found'}); }

            const body = new URLSearchParams();
            body.set(hidden.name, hidden.value);
            body.set('from', '{$date}');
            body.set('to', '{$date}');

            const res = await fetch(window.location.href, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: body.toString(),
            });

            const contentType = res.headers.get('content-type') || '';
            let text = null;
            if (contentType.includes('json') || contentType.includes('text') || contentType.includes('csv')) {
                text = await res.text();
            }

            return JSON.stringify({status: res.status, contentType, text});
        }
        JS;

        $chromiumPath = config('app.chromium_path')
            ?: (file_exists('/usr/bin/google-chrome') ? '/usr/bin/google-chrome'
            : (file_exists('/usr/bin/google-chrome-stable') ? '/usr/bin/google-chrome-stable'
            : (file_exists('/usr/bin/chromium') ? '/usr/bin/chromium'
            : (file_exists('/usr/bin/chromium-browser') ? '/usr/bin/chromium-browser'
            : null))));

        $browsershot = Browsershot::url(self::EXPORT_URL)
            ->setCustomTempPath(storage_path('app/browsershot-tmp'))
            ->timeout(30)
            ->noSandbox()
            ->addChromiumArguments(['disable-dev-shm-usage', 'disable-gpu']);

        if ($chromiumPath) {
            $browsershot->setChromePath($chromiumPath);
        }

        return $browsershot->evaluate($js);
    }

    /** يحاول قراءة الرد كـJSON أولاً، ثم كـCSV/نص بسيط. */
    private function parseRates(string $text, string $expectedDate): array
    {
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $this->extractFromArray($decoded, $expectedDate);
        }

        return $this->parseFromDelimitedText($text, $expectedDate);
    }

    private function extractFromArray(array $data, string $expectedDate): array
    {
        // شكل مسطّح محتمل: {"rates": {"JOD":.., "USD":..}, "date":..} أو {"JOD":.., "USD":.., "date":..}
        $flat = $data['rates'] ?? $data;
        if (isset($flat['JOD'], $flat['USD'])) {
            $date = $data['date'] ?? $flat['date'] ?? $expectedDate;
            $this->assertFreshDate($date, $expectedDate);

            return ['JOD' => (float) $flat['JOD'], 'USD' => (float) $flat['USD'], 'date' => $date];
        }

        // أو قائمة صفوف [{currency: "JOD", rate: ..}, {currency: "USD", rate: ..}]
        $rates = [];
        $date  = null;

        foreach ($data as $row) {
            if (! is_array($row)) {
                continue;
            }
            $currency = strtoupper((string) ($row['currency'] ?? $row['Currency'] ?? ''));
            $rate     = $row['rate'] ?? $row['Rate'] ?? $row['value'] ?? null;
            $rowDate  = $row['date'] ?? $row['Date'] ?? null;

            if (in_array($currency, ['JOD', 'USD'], true) && $rate !== null) {
                $rates[$currency] = (float) $rate;
                $date = $date ?? $rowDate;
            }
        }

        if (! isset($rates['JOD'], $rates['USD'])) {
            throw new \RuntimeException('pma_scrape_missing_fields: لم يُعثر على JOD/USD بالرد.');
        }

        $date = $date ?? $expectedDate;
        $this->assertFreshDate($date, $expectedDate);

        return ['JOD' => $rates['JOD'], 'USD' => $rates['USD'], 'date' => $date];
    }

    private function parseFromDelimitedText(string $text, string $expectedDate): array
    {
        $rates = [];
        $date  = null;

        foreach (preg_split('/\r\n|\r|\n/', trim($text)) as $line) {
            $cols = str_contains($line, ',') ? str_getcsv($line) : preg_split('/\s+/', trim($line));
            $cols = array_map('trim', $cols);

            foreach ($cols as $i => $col) {
                if (preg_match('/^(JOD|USD)$/i', $col) && isset($cols[$i + 1]) && is_numeric($cols[$i + 1])) {
                    $rates[strtoupper($col)] = (float) $cols[$i + 1];
                }
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $col)) {
                    $date = $date ?? $col;
                }
            }
        }

        if (! isset($rates['JOD'], $rates['USD'])) {
            throw new \RuntimeException('pma_scrape_missing_fields: لم يُعثر على JOD/USD بالنص المُستلم.');
        }

        $date = $date ?? $expectedDate;
        $this->assertFreshDate($date, $expectedDate);

        return ['JOD' => $rates['JOD'], 'USD' => $rates['USD'], 'date' => $date];
    }

    /** يتحقق أن تاريخ الجدول هو اليوم الحالي وليس بيانات قديمة مخبَّأة (متطلب صريح). */
    private function assertFreshDate(string $rowDate, string $expectedDate): void
    {
        if (substr($rowDate, 0, 10) !== $expectedDate) {
            throw new \RuntimeException("pma_scrape_stale_date: التاريخ بالرد ({$rowDate}) ليس اليوم ({$expectedDate}).");
        }
    }
}
