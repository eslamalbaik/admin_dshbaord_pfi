<?php

namespace App\Services;

use Spatie\Browsershot\Browsershot;

/**
 * جلب أسعار الصرف الرسمية من أداة سلطة النقد الفلسطينية (PMA) — REQ-17.
 * العملة الأساس ILS (نفس تسعير الاتحاد محلياً)، والمخرَج: كم JOD وكم USD يعادل 1 ILS.
 *
 * ملاحظة هامة: أداة التصدير (wcur.pma.ps/ar/webtools/currency/export) محمية بحقل
 * CSRF عشوائي الاسم/القيمة يتغيّر كل تحميل صفحة (لا يوجد API عام موثّق)، ولذلك
 * يُرسَل النموذج من داخل سياق الصفحة نفسها عبر متصفح حقيقي (Browsershot).
 *
 * شكل الرد — مرصود فعلياً بتاريخ 2026-09-24 (كان مجهولاً قبلها لأن الخدمة كانت
 * ترجع 503 باستمرار): الخدمة تعود بـHTTP 200 وملف **Excel (xlsx)** لا JSON/CSV،
 * بالأعمدة: التاريخ | العملة | الشراء | البيع | الوسطي، وبأزواج مثل USD/ILS و
 * USD/JOD. نقرأه بـPhpSpreadsheet ونأخذ عمود "الوسطي".
 *
 * يبقى مسار JSON/CSV مدعوماً أيضاً تحسّباً لتغيّر الصيغة مستقبلاً؛ وأي شكل غير
 * معروف يُرفض صراحة بدل تخمين قراءته، فيذهب لمسار fallback+تنبيه في rates:fetch-pma.
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

        // rates['JOD']/['USD'] هنا = "1 ILS = كم JOD/USD" (نفس صيغة مواصفة REQ-17 الأصلية، الأساس ILS)
        if (! empty($result['text'])) {
            $rates = $this->parseRates($result['text'], $today);
        } elseif (! empty($result['binary'])) {
            $rates = $this->parseExcelRates(base64_decode($result['binary']), $today);
        } else {
            throw new \RuntimeException(
                'pma_scrape_unrecognized_format: رد فارغ أو بنوع محتوى غير مدعوم ('
                . ($result['contentType'] ?? 'unknown') . ').',
            );
        }

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
            let binary = null;
            if (contentType.includes('json') || contentType.includes('text') || contentType.includes('csv')) {
                text = await res.text();
            } else {
                // الرد المرصود فعلياً هو xlsx ثنائي — ننقله كـbase64 لأن JSON لا يحمل بايتات خاماً
                const bytes = new Uint8Array(await res.arrayBuffer());
                let bin = '';
                for (let i = 0; i < bytes.length; i++) { bin += String.fromCharCode(bytes[i]); }
                binary = btoa(bin);
            }

            return JSON.stringify({status: res.status, contentType, text, binary});
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

        // التغليف بـIIFE ضروري ولا يجوز حذفه: browser.cjs يمرّر النص كما هو إلى
        // page.evaluate()، وPuppeteer الحديث يُقيّم النص كـ«تعبير» لا كدالة يستدعيها.
        // فـ"async () => {...}" كتعبير ينتج كائن دالة، والدوال غير قابلة للتسلسل في
        // JSON فيعود result = {} — ثم ينهار Browsershot بـ:
        //   Cannot assign array to property ChromiumResult::$result of type string
        // وهي رسالة لا تشير إطلاقاً إلى السبب. الأقواس + () تجعله يُستدعى فيعود نصاً.
        return $browsershot->evaluate("({$js})()");
    }

    /**
     * يقرأ ملف xlsx الذي تعيده أداة التصدير فعلياً.
     * الأعمدة المرصودة: 0=التاريخ (yyyy/mm/dd) · 1=العملة (زوج مثل USD/ILS) · 2=الشراء · 3=البيع · 4=الوسطي
     * الجدول يسعّر كل شيء مقابل الدولار، بينما REQ-17 تحتاج الأساس ILS — فنشتق:
     *   1 ILS = (USD/JOD) ÷ (USD/ILS) دينار   ·   1 ILS = 1 ÷ (USD/ILS) دولار
     *
     * @return array{JOD: float, USD: float, date: string}
     */
    private function parseExcelRates(string $bytes, string $expectedDate): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'pma_') . '.xlsx';
        file_put_contents($tmp, $bytes);

        try {
            $rows = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp)
                ->getActiveSheet()
                ->toArray(null, true, false, false);
        } catch (\Throwable $e) {
            throw new \RuntimeException('pma_scrape_excel_unreadable: تعذّرت قراءة ملف Excel — ' . $e->getMessage());
        } finally {
            @unlink($tmp);
        }

        $pairs = [];
        $fileDate = null;

        foreach ($rows as $row) {
            $pair = trim((string) ($row[1] ?? ''));
            $mid  = $row[4] ?? null;

            if ($pair === '' || ! is_numeric($mid)) {
                continue; // صف العناوين أو صف فارغ
            }

            $pairs[strtoupper($pair)] = (float) $mid;
            $fileDate ??= str_replace('/', '-', trim((string) ($row[0] ?? '')));
        }

        foreach (['USD/ILS', 'USD/JOD'] as $needed) {
            if (empty($pairs[$needed])) {
                throw new \RuntimeException("pma_scrape_excel_missing_pair: الزوج {$needed} غير موجود في ملف التصدير.");
            }
        }

        // تاريخ قديم يعني أن سلطة النقد لم تنشر سعر اليوم بعد — نفشل صراحةً بدل
        // تخزين سعر الأمس على أنه سعر اليوم.
        if ($fileDate !== null && $fileDate !== $expectedDate) {
            throw new \RuntimeException(
                "pma_scrape_stale_date: التاريخ في الملف ({$fileDate}) ليس اليوم ({$expectedDate}).",
            );
        }

        return [
            'JOD'  => $pairs['USD/JOD'] / $pairs['USD/ILS'],
            'USD'  => 1 / $pairs['USD/ILS'],
            'date' => $fileDate ?? $expectedDate,
        ];
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
