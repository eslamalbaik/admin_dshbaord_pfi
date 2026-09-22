<?php

namespace App\Console\Commands;

use App\Models\ExchangeRate;
use App\Models\User;
use App\Services\PmaRateScraperService;
use App\Services\SupabaseRateProxyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * جلب أسعار الصرف الرسمية اليومية من سلطة النقد الفلسطينية (REQ-17).
 * أيام الاثنين–الجمعة فقط (schedule بـroutes/console.php)؛ السبت/الأحد لا كشط —
 * يُعتمد تلقائياً آخر سعر محفوظ (نفس سلوك ExchangeRateService::latest() الافتراضي، لا حاجة كتابة).
 *
 * ترتيب المحاولة: مصدر Supabase الثانوي (سريع، بلا Chromium — لكن غير رسمي وغير مضمون
 * الاستمرارية) أولاً، فإن فشل أو رجع تاريخاً غير اليوم → محاولة الموقع الرسمي مباشرة
 * (Browsershot). فشل الاثنين معاً → آخر سعر محفوظ + تنبيه الإدارة.
 */
class FetchPmaExchangeRates extends Command
{
    protected $signature = 'rates:fetch-pma';

    protected $description = 'جلب أسعار الصرف الرسمية اليومية من سلطة النقد الفلسطينية (PMA)، مع fallback لآخر سعر محفوظ عند الفشل';

    public function handle(SupabaseRateProxyService $proxy, PmaRateScraperService $scraper): int
    {
        // الجمعة والسبت: كلا المصدرين لا يُحدَّثان فعلياً (تأكيد ميداني) — تخطٍّ بدل محاولة
        // تفشل حتماً بـ"تاريخ قديم" وتُطلق تنبيه أدمن كاذب أسبوعياً. باقي الأسبوع عادي.
        if (now()->isFriday() || now()->isSaturday()) {
            $this->info('الجمعة/السبت — المصادر لا تُحدَّث، لا كشط، يُعتمد آخر سعر محفوظ.');
            Log::channel('finance')->info('pma_rates.skipped_fri_sat');

            return self::SUCCESS;
        }

        $attempts = [
            'supabase_proxy' => $proxy,
            'pma_direct'     => $scraper,
        ];

        $lastError = null;

        foreach ($attempts as $source => $service) {
            try {
                $rates = $service->fetch(); // ['ils_to_jod' => .., 'usd_to_jod' => .., 'date' => ..]

                ExchangeRate::create(['currency' => 'ILS', 'rate_to_jod' => $rates['ils_to_jod'], 'fetched_at' => now(), 'source' => $source]);
                ExchangeRate::create(['currency' => 'USD', 'rate_to_jod' => $rates['usd_to_jod'], 'fetched_at' => now(), 'source' => $source]);
                Cache::forget('exchange_rate.ILS');
                Cache::forget('exchange_rate.USD');

                Log::channel('finance')->info('pma_rates.fetched', [
                    'source' => $source, 'date' => $rates['date'],
                    'ils_to_jod' => $rates['ils_to_jod'], 'usd_to_jod' => $rates['usd_to_jod'],
                ]);
                $this->info("تم جلب أسعار الصرف بنجاح عبر [{$source}] — ILS→JOD={$rates['ils_to_jod']}, USD→JOD={$rates['usd_to_jod']}");

                return self::SUCCESS;
            } catch (\Throwable $e) {
                $lastError = "[{$source}] " . $this->simplifyErrorMessage($e->getMessage());
                Log::channel('finance')->warning('pma_rates.source_failed', ['source' => $source, 'error' => $e->getMessage()]);
            }
        }

        Log::channel('finance')->critical('pma_rates.fetch_failed', ['error' => $lastError]);
        $this->error("فشل جلب أسعار الصرف من كل المصادر: {$lastError} — الاعتماد على آخر سعر محفوظ.");

        $admins = User::where('role', 'admin')->get();
        if ($admins->isNotEmpty()) {
            Notification::send($admins, new \App\Notifications\PmaRatesFetchFailedNotification($lastError));
        }

        return self::FAILURE;
    }

    /** تبسيط رسالة الخطأ الطويلة من Browsershot/Puppeteer لتكون واضحة وقابلة للقراءة */
    private function simplifyErrorMessage(string $msg): string
    {
        // استخرج السبب الأساسي إن أمكن
        if (preg_match('/pma_scrape_\w+|supabase_proxy_\w+: (.+?)(?:\n|$)/', $msg, $m)) {
            return $m[1] ?? 'فشل غير متوقع';
        }
        if (preg_match('/^[^:]+: (.+?)(?:\n|$)/', $msg, $m)) {
            return $m[1];
        }
        // حد أقصى 120 حرف إذا كانت الرسالة طويلة
        return strlen($msg) > 120 ? substr($msg, 0, 120) . '…' : $msg;
    }
}
