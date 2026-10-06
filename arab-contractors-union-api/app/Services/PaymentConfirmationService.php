<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentAllocation;
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
        PaymentAllocation::record($payment, $due, $amount);

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
     * الرصيد بينشال لحاله (balancesQuery بتحسب الدفعات المؤكَّدة بس)، وتسديد الذمم وتجديد
     * العضوية بينعكسوا بـreverseEffects(). الممنوع: تسديد قديم ما إله سجل توزيع، واشتراك آليات.
     */
    public function revertBlocker(Payment $payment): ?string
    {
        if ($payment->status !== 'paid') {
            return null;
        }

        // التسديد بيترجع بس لو كله مسجّل بـpayment_allocations (دفعات من 6/10/2026 وطالع)
        $used = round((float) $payment->used_amount_jod, 2);
        if ($used > 0 && round((float) $payment->allocations()->sum('amount_jod'), 2) !== $used) {
            return 'هذه الدفعة سدّدت ذمماً قبل تفعيل سجل التوزيع، فلا يمكن ترجيع تسديدها تلقائياً — ألغِ التسديد من شاشة الذمم أولاً.';
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
     * عكس آثار دفعة مؤكَّدة: تجديد العضوية (لو رسوم عضوية)، وتسديد الذمم اللي سدّدتها
     * فترجع الذمم مستحقة والرصيد يرجع صفر
     */
    private function reverseEffects(Payment $payment, User $authUser): void
    {
        if ($payment->type === 'membership_fee' && $payment->membership_id) {
            app(MembershipRenewalService::class)->revertFromPayment($payment);

            AuditLogService::record(
                $authUser,
                'membership.renewal_reversed',
                $payment->membership,
                ['payment_id' => $payment->id, 'contractor_id' => $payment->contractor_id],
            );
        }

        foreach ($payment->allocations()->with('due')->lockForUpdate()->get() as $allocation) {
            $due = $allocation->due;
            if ($due) {
                $due->reversePayment((float) $allocation->amount_jod);

                AuditLogService::record(
                    $authUser,
                    'due.settlement_reversed',
                    $due,
                    ['contractor_id' => $due->contractor_id, 'amount_jod' => (float) $allocation->amount_jod, 'payment_id' => $payment->id],
                );
            }
            $allocation->delete();
        }

        $payment->update(['used_amount_jod' => 0]);
    }

    /**
     * إرجاع الدفعة لـ"قيد المراجعة"
     */
    private function reopen(Payment $payment, User $authUser): void
    {
        if ($payment->status === 'paid') {
            $this->reverseEffects($payment, $authUser);
        }

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

        DB::transaction(function () use ($payment, $reason, $authUser) {
            if ($payment->status === 'paid') {
                $this->reverseEffects($payment, $authUser);
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
        });
    }
}
