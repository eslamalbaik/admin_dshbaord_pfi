<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\EquipmentPackage;
use App\Models\ContractorEquipmentSubscription;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PaymentConfirmationService
{
    public function __construct(
        private ExchangeRateService $exchangeRateService,
        private ReceiptPdfService $receiptPdfService
    ) {}

    /**
     * تأكيد عملية الدفع وتنفيذ جميع الآثار الجانبية (Side Effects)
     */
    public function confirm(Payment $payment, array $data, User $authUser): void
    {
        $currency   = strtoupper($payment->currency ?? 'JOD');
        $rate       = null;
        $rateSource = null;

        if ($currency !== 'JOD') {
            if (isset($data['exchange_rate'])) {
                $rate       = (float) $data['exchange_rate'];
                $rateSource = 'manual';
            } else {
                $latest = $this->exchangeRateService->latest($currency);
                if (! $latest) {
                    throw new \Exception("لا يوجد سعر صرف معتمد لعملة {$currency} — أدخل السعر يدوياً أو شغّل rates:fetch.");
                }
                $rate       = (float) $latest->rate_to_jod;
                $rateSource = $latest->source;
            }
        }

        DB::transaction(function () use ($data, $payment, $currency, $rate, $rateSource, $authUser) {
            $updateData = [
                'status'           => 'paid',
                'exchange_rate'    => $rate,
                'amount_jod'       => $this->exchangeRateService->convertToJod((float) $payment->amount, $currency, $rate ?? 1.0),
                'rate_source'      => $currency === 'JOD' ? null : $rateSource,
                'confirmed_by'     => $authUser->id,
                'confirmed_at'     => now(),
                'paid_at'          => now(),
                'rejection_reason' => null,
            ];

            if (isset($data['receipt_image']) && $data['receipt_image'] instanceof UploadedFile) {
                if ($payment->receipt_image) {
                    Storage::disk('public')->delete($payment->receipt_image);
                }
                $updateData['receipt_image'] = $data['receipt_image']->store('payment-receipts', 'public');
            }

            $payment->update($updateData);

            $this->settleLinkedDue($payment, $authUser);

            // إطلاق حدث للمكونات الأخرى (Membership, Equipment) للتفاعل مع الدفعة باستقلالية
            \App\Events\PaymentConfirmed::dispatch($payment, $authUser->id);

            // إرسال عملية توليد وصل PDF إلى الخلفية لتسريع استجابة النظام
            \App\Jobs\GeneratePaymentReceiptJob::dispatch($payment->id);

            AuditLogService::record(
                $authUser,
                'payment.confirmed',
                $payment,
                ['amount' => $payment->amount, 'currency' => $currency, 'amount_jod' => $payment->amount_jod],
            );
        });
    }

    /**
     * تحويل مرفوع من التطبيق لتسديد ذمة محددة: يُسدَّد من الذمة نفسها فور الاعتماد (بحدود
     * المتبقي عليها)، وأي فائض يضل رصيداً للمقاول (used_amount_jod أقل من amount_jod).
     */
    private function settleLinkedDue(Payment $payment, User $authUser): void
    {
        if ($payment->type !== 'dues_payment' || ! $payment->contractor_due_id) {
            return;
        }

        $due = $payment->due()->lockForUpdate()->first();
        if (! $due || $due->status === 'paid') {
            return;
        }

        $available = round((float) $payment->amount_jod - (float) $payment->used_amount_jod, 2);
        $amount    = min($available, $due->remaining_jod);
        if ($amount <= 0) {
            return;
        }

        $payment->increment('used_amount_jod', $amount);
        $due->applyPayment($amount);

        AuditLogService::record(
            $authUser,
            'due.settled',
            $due,
            ['contractor_id' => $due->contractor_id, 'amount_jod' => $amount, 'payment_id' => $payment->id],
        );
    }

    /**
     * رفض الدفعة
     */
    public function reject(Payment $payment, string $reason, User $authUser): void
    {
        $payment->update([
            'status'           => 'rejected',
            'confirmed_by'     => $authUser->id,
            'confirmed_at'     => now(),
            'rejection_reason' => $reason,
        ]);

        AuditLogService::record(
            $authUser,
            'payment.rejected',
            $payment,
            ['reason' => $reason],
        );
    }
}
