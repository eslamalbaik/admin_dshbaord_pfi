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
        // تأكيد دفعة مؤكَّدة أصلاً كان يعيد تجديد العضوية ويضاعف أثرها — ممنوع
        if ($payment->status === 'paid') {
            throw new \Exception('هذه الدفعة مؤكَّدة مسبقاً.');
        }

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
     * سبب منع إرجاع دفعة مؤكَّدة (لقيد المراجعة أو مرفوضة)، أو null لو إرجاعها آمن.
     *
     * الإرجاع الآمن حالياً = دفعة ما إلها أي أثر غير رصيدها: الرصيد بيتحسب من الدفعات
     * المؤكَّدة بس (ContractorBalanceController::balancesQuery)، فبمجرد ما تطلع من "paid"
     * بينشال رصيدها لحاله. أما الدفعة اللي سدّدت ذمم أو جدّدت عضوية أو فعّلت اشتراك آليات،
     * فما في سجل توزيع يخلّينا نرجّع أثرها بدقة، فبنمنعها لحد ما يتقرر كيف تنعكس.
     */
    public function revertBlocker(Payment $payment): ?string
    {
        if ($payment->status !== 'paid') {
            return null;
        }

        if ((float) $payment->used_amount_jod > 0) {
            return 'هذه الدفعة سدّدت ذمماً على المقاول، فلا يمكن تغيير حالتها قبل إلغاء التسديد.';
        }

        if ($payment->type === 'membership_fee' && $payment->membership_id) {
            return 'هذه الدفعة جدّدت عضوية المقاول، فلا يمكن تغيير حالتها من هنا.';
        }

        if ($payment->type === 'equipment_subscription'
            && ContractorEquipmentSubscription::where('payment_id', $payment->id)->exists()) {
            return 'هذه الدفعة فعّلت اشتراكاً في سوق الآليات، فلا يمكن تغيير حالتها من هنا.';
        }

        return null;
    }

    /**
     * "تغيير الحالة" من قائمة الإجراءات: ينفّذ الانتقال ويسجّل السبب ومين غيّر.
     * - إلى مؤكَّدة: نفس مسار confirm() بكل آثاره.
     * - إلى مرفوضة/قيد المراجعة: من مؤكَّدة بس لو revertBlocker() ما منع.
     */
    public function changeStatus(Payment $payment, string $to, string $reason, array $data, User $authUser): void
    {
        $from = $payment->status;

        if ($from === $to) {
            throw new \Exception('الدفعة بهذه الحالة أصلاً.');
        }

        if ($blocker = $this->revertBlocker($payment)) {
            throw new \Exception($blocker);
        }

        DB::transaction(function () use ($payment, $from, $to, $reason, $data, $authUser) {
            match ($to) {
                'paid'     => $this->confirm($payment, $data, $authUser),
                'rejected' => $this->reject($payment, $reason, $authUser),
                'pending'  => $this->reopen($payment, $authUser),
            };

            $payment->statusChanges()->create([
                'from_status' => $from,
                'to_status'   => $to,
                'reason'      => $reason,
                'changed_by'  => $authUser->id,
            ]);

            AuditLogService::record(
                $authUser,
                'payment.status_changed',
                $payment,
                ['from' => $from, 'to' => $to, 'reason' => $reason],
            );
        });
    }

    /**
     * إرجاع الدفعة لـ"قيد المراجعة"
     */
    private function reopen(Payment $payment, User $authUser): void
    {
        $payment->update([
            'status'           => 'pending',
            'confirmed_by'     => $authUser->id,
            'confirmed_at'     => now(),
            'paid_at'          => null,
            'rejection_reason' => null,
        ]);
    }

    /**
     * رفض الدفعة
     */
    public function reject(Payment $payment, string $reason, User $authUser): void
    {
        if ($blocker = $this->revertBlocker($payment)) {
            throw new \Exception($blocker);
        }

        $payment->update([
            'status'           => 'rejected',
            'confirmed_by'     => $authUser->id,
            'confirmed_at'     => now(),
            'paid_at'          => null,
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
