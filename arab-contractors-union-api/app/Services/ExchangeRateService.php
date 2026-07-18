<?php

namespace App\Services;

use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExchangeRateService
{
    /** العملات المدعومة للدفع إلى جانب الدينار الأردني */
    public const CURRENCIES = ['ILS', 'USD'];

    private const API_URL = 'https://open.er-api.com/v6/latest/JOD';

    /**
     * آخر سعر معتمد للعملة (1 وحدة = كم JOD) — كاش ساعة.
     */
    public function latest(string $currency): ?ExchangeRate
    {
        $currency = strtoupper($currency);

        if ($currency === 'JOD') {
            return null;
        }

        return Cache::remember("exchange_rate.{$currency}", 3600, function () use ($currency) {
            return ExchangeRate::where('currency', $currency)
                ->orderByDesc('fetched_at')
                ->first();
        });
    }

    /**
     * تحويل مبلغ بعملة معينة إلى الدينار الأردني بسعر محدد.
     */
    public function convertToJod(float $amount, string $currency, float $rateToJod): float
    {
        if (strtoupper($currency) === 'JOD') {
            return round($amount, 2);
        }

        return round($amount * $rateToJod, 2);
    }

    /**
     * جلب الأسعار من الـ API الخارجي وتخزينها (تُستدعى يومياً من الجدولة).
     * عند الفشل يبقى آخر سعر محفوظ معتمداً.
     */
    public function fetch(): array
    {
        try {
            $response = Http::timeout(15)->get(self::API_URL);

            if (! $response->successful() || $response->json('result') !== 'success') {
                throw new \RuntimeException('Unexpected response: ' . $response->status());
            }

            $rates  = $response->json('rates', []);
            $stored = [];

            foreach (self::CURRENCIES as $currency) {
                // الـ API يعيد JOD→العملة، ونحتاج العملة→JOD
                $jodToCurrency = (float) ($rates[$currency] ?? 0);
                if ($jodToCurrency <= 0) {
                    continue;
                }

                $rate = ExchangeRate::create([
                    'currency'    => $currency,
                    'rate_to_jod' => round(1 / $jodToCurrency, 6),
                    'fetched_at'  => now(),
                    'source'      => 'api',
                ]);

                Cache::forget("exchange_rate.{$currency}");
                $stored[$currency] = $rate->rate_to_jod;
            }

            return $stored;
        } catch (\Throwable $e) {
            Log::channel('finance')->warning('exchange_rates.fetch_failed', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * إدخال سعر يدوي من المحاسب/الأدمن (override).
     */
    public function setManual(string $currency, float $rateToJod): ExchangeRate
    {
        $rate = ExchangeRate::create([
            'currency'    => strtoupper($currency),
            'rate_to_jod' => $rateToJod,
            'fetched_at'  => now(),
            'source'      => 'manual',
        ]);

        Cache::forget('exchange_rate.' . strtoupper($currency));

        return $rate;
    }
}
