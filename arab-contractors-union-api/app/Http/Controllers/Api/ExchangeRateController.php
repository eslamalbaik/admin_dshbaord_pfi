<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\ExchangeRate;
use App\Services\ExchangeRateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ExchangeRateController extends Controller
{
    use ApiResponseTrait;

    /** GET /api/v1/dashboard/exchange-rates */
    public function index(ExchangeRateService $service)
    {
        $latest = collect(ExchangeRateService::CURRENCIES)
            ->mapWithKeys(function ($currency) use ($service) {
                $rate = $service->latest($currency);

                return [$currency => $rate ? [
                    'rate_to_jod' => $rate->rate_to_jod,
                    'fetched_at'  => $rate->fetched_at,
                    'source'      => $rate->source,
                ] : null];
            });

        return $this->success([
            'latest'  => $latest,
            'history' => ExchangeRate::orderByDesc('fetched_at')->limit(60)->get(),
        ]);
    }

    /** POST /api/v1/dashboard/exchange-rates — سعر يدوي (override) */
    public function store(Request $request, ExchangeRateService $service)
    {
        $data = $request->validate([
            'currency'    => 'required|in:ILS,USD',
            'rate_to_jod' => 'required|numeric|min:0.0001|max:1000',
        ]);

        $rate = $service->setManual($data['currency'], (float) $data['rate_to_jod']);

        Log::channel('finance')->info('exchange_rate.manual_override', [
            'user_id'     => Auth::id(),
            'currency'    => $rate->currency,
            'rate_to_jod' => $rate->rate_to_jod,
        ]);

        return $this->success($rate->toArray(), 'تم اعتماد سعر الصرف اليدوي.', 201);
    }
}
