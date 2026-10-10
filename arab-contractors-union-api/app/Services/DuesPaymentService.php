<?php

namespace App\Services;

use App\Models\Contractor;
use App\Models\ContractorDue;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Support\Facades\DB;

class DuesPaymentService
{
    public function __construct(private ExchangeRateService $exchangeRateService)
    {
    }

    /**
     * معالجة دفعة جديدة وتوزيعها تلقائياً على الذمم الأقدم، ثم على الغرامات المفتوحة.
     * الغرامات جزء من "المتبقي" بجدول الذمم وبالأرصدة، فلو ضل فائض الدفعة رصيد له
     * والغرامة مفتوحة، بيطلع المتبقي بجدول الذمم أكبر من صافي الأرصدة.
     */
    public function processPayment(Contractor $contractor, array $data, int $authId): array
    {
        $currency = strtoupper($data['currency'] ?? 'JOD');

        // تحديد سعر الصرف
        $rate       = null;
        $rateSource = null;
        if ($currency !== 'JOD') {
            if (isset($data['exchange_rate'])) {
                $rate       = (float) $data['exchange_rate'];
                $rateSource = 'manual';
            } else {
                $latest = $this->exchangeRateService->latest($currency);
                if (! $latest) {
                    throw new \Exception("لا يوجد سعر صرف معتمد لعملة {$currency} — أدخل السعر يدوياً.");
                }
                $rate       = (float) $latest->rate_to_jod;
                $rateSource = $latest->source;
            }
        }

        $amountJod = $this->exchangeRateService->convertToJod((float) $data['amount'], $currency, $rate ?? 1.0);

        return DB::transaction(function () use ($contractor, $data, $currency, $rate, $rateSource, $amountJod, $authId) {
            $payment = Payment::create([
                'contractor_id'    => $contractor->id,
                'amount'           => $data['amount'],
                'currency'         => $currency,
                'exchange_rate'    => $rate,
                'amount_jod'       => $amountJod,
                'used_amount_jod'  => 0,
                'rate_source'      => $currency === 'JOD' ? null : $rateSource,
                'type'             => 'dues_payment',
                'status'           => 'paid',
                'method'           => $data['method'] ?? 'cash',
                'reference_number' => $data['reference_number'] ?? null,
                'receipt_image'    => $data['receipt_image'] ?? null,
                'notes'            => $data['notes'] ?? null,
                'submitted_at'     => now(),
                'confirmed_by'     => $authId,
                'confirmed_at'     => now(),
                'paid_at'          => now(),
            ]);

            // التوزيع على الذمم الأقدم
            $remaining = $amountJod;
            $settled   = [];

            $dues = ContractorDue::sortOldestFirst($contractor->dues()->outstanding()->lockForUpdate()->get());

            foreach ($dues as $due) {
                if ($remaining <= 0) {
                    break;
                }

                $applied = min($remaining, $due->remaining_jod);
                $due->applyPayment($applied);
                PaymentAllocation::record($payment, $due, $applied);
                $remaining = round($remaining - $applied, 2);

                $settled[] = [
                    'due_id'      => $due->id,
                    'year'        => $due->year,
                    'description' => $due->description,
                    'applied_jod' => $applied,
                    'status'      => $due->status,
                ];
            }

            $penalties = $remaining > 0
                ? $contractor->penalties()
                    ->whereIn('status', ['unpaid', 'partially_paid'])
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->get()
                : collect();

            foreach ($penalties as $penalty) {
                if ($remaining <= 0) {
                    break;
                }

                $applied = min($remaining, $penalty->remaining);
                if ($applied <= 0) {
                    continue;
                }

                $penalty->applyPayment($applied);
                PaymentAllocation::recordPenalty($payment, $penalty, $applied);
                $remaining = round($remaining - $applied, 2);

                $settled[] = [
                    'penalty_id'  => $penalty->id,
                    'year'        => null,
                    'description' => 'غرامة: ' . $penalty->reason,
                    'applied_jod' => $applied,
                    'status'      => $penalty->status,
                ];
            }

            // تحديث المبلغ المستخدم من الدفعة
            $payment->update(['used_amount_jod' => $amountJod - $remaining]);

            return [
                'payment_id'     => $payment->id,
                'amount_jod'     => $amountJod,
                'applied'        => $settled,
                'unapplied_jod'  => $remaining,
                'currency'       => $currency,
                'exchange_rate'  => $rate,
            ];
        });
    }
}
