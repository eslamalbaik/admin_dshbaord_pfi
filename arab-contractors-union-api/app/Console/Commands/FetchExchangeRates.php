<?php

namespace App\Console\Commands;

use App\Services\ExchangeRateService;
use Illuminate\Console\Command;

class FetchExchangeRates extends Command
{
    protected $signature = 'rates:fetch';

    protected $description = 'جلب أسعار صرف ILS/USD مقابل الدينار الأردني من الـ API الخارجي';

    public function handle(ExchangeRateService $service): int
    {
        $stored = $service->fetch();

        if (empty($stored)) {
            $this->warn('فشل جلب الأسعار — يبقى آخر سعر محفوظ معتمداً.');

            return self::FAILURE;
        }

        foreach ($stored as $currency => $rate) {
            $this->info("{$currency} → JOD: {$rate}");
        }

        return self::SUCCESS;
    }
}
