<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * مصدر ثانوي/أسرع لأسعار الصرف — endpoint غير رسمي (Supabase Edge Function طرف ثالث،
 * وليس pma.ps) وجده المستخدم يدوياً. لا ضمان استمرارية أو ملكية معروفة — لذلك يُجرَّب
 * أولاً بأمر rates:fetch-pma (سريع، بلا Chromium)، وعند فشله/عدم تحديثه اليوم يُستكمَل
 * بمحاولة PmaRateScraperService (الموقع الرسمي مباشرة) كخط ثانٍ قبل fallback الأخير.
 *
 * شكل الرد: {"date": "الخميس 2026/08/06", "rates": [{"code":"USD","buy":..,"sell":..}, ...]}
 * كل سعر = "1 [code] = X ILS" (وليس العكس) — تحقّقتُ رياضياً من الاتجاه عبر مطابقة نسبة
 * USD/JOD الناتجة (~0.709) مع ربط الدينار الرسمي بالدولار الثابت منذ 1995.
 */
class SupabaseRateProxyService
{
    private const URL = 'https://fayupjvyvxedvgathafk.supabase.co/functions/v1/fetch-exchange-rates';

    /** حد معقول لسعر الدولار مقابل الدينار — الدينار مربوط بالدولار رسمياً (~0.708-0.709) منذ 1995 */
    private const USD_JOD_SANITY_MIN = 0.65;
    private const USD_JOD_SANITY_MAX = 0.75;

    /** @return array{ils_to_jod: float, usd_to_jod: float, date: string} — جاهزة للتخزين مباشرة */
    public function fetch(): array
    {
        $response = Http::timeout(10)->get(self::URL);

        if (! $response->successful() || ! ($response->json('success') === true)) {
            throw new \RuntimeException('supabase_proxy_failed: HTTP ' . $response->status());
        }

        $rawDate = (string) $response->json('date', '');
        if (! preg_match('/(\d{4})\/(\d{2})\/(\d{2})/', $rawDate, $m)) {
            throw new \RuntimeException("supabase_proxy_bad_date: '{$rawDate}'");
        }
        $date = "{$m[1]}-{$m[2]}-{$m[3]}";

        $today = now()->toDateString();
        if ($date !== $today) {
            throw new \RuntimeException("supabase_proxy_stale_date: التاريخ بالرد ({$date}) ليس اليوم ({$today}).");
        }

        $rows = collect($response->json('rates', []))->keyBy('code');
        if (! $rows->has('JOD') || ! $rows->has('USD')) {
            throw new \RuntimeException('supabase_proxy_missing_fields: لا يوجد JOD/USD بالرد.');
        }

        // سعر بيع الدينار (قرار الإدارة 2026-10-08) — نفس PmaRateScraperService: البنك يبيع الدينار
        // بسعر JOD.sell شيكل، ويشتري الدولار بسعر USD.buy شيكل قبل ما يحوّله لدينار.
        $jodSellInIls = (float) $rows['JOD']['sell'];
        $usdBuyInIls  = (float) $rows['USD']['buy'];

        if ($jodSellInIls <= 0 || $usdBuyInIls <= 0) {
            throw new \RuntimeException('supabase_proxy_invalid_rate: قيمة سعر صفر أو سالبة.');
        }

        $ilsToJod = 1 / $jodSellInIls;          // 1 ILS = كم JOD
        $usdToJod = $usdBuyInIls * $ilsToJod;   // 1 USD = X ILS = X * (JOD لكل ILS)

        if ($usdToJod < self::USD_JOD_SANITY_MIN || $usdToJod > self::USD_JOD_SANITY_MAX) {
            throw new \RuntimeException(
                "supabase_proxy_sanity_check_failed: USD→JOD المحسوب ({$usdToJod}) خارج نطاق ربط الدينار بالدولار المتوقع.",
            );
        }

        return ['ils_to_jod' => round($ilsToJod, 6), 'usd_to_jod' => round($usdToJod, 6), 'date' => $date];
    }
}
