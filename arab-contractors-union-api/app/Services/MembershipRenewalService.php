<?php

namespace App\Services;

use App\Models\Membership;
use App\Models\Payment;

/**
 * منطق تجديد العضوية الموحّد — يُستخدم من موافقة الأدمن اليدوية (MembershipController::approve)
 * ومن تأكيد الدفعة (PaymentController::confirm) على حد سواء، لضمان نفس قاعدة "الموعد الثابت".
 */
class MembershipRenewalService
{
    /**
     * يُرسي starts_at/expires_at على تاريخ آخر عضوية سابقة للمقاول (لا على تاريخ المعالجة نفسه)
     * حتى لا ينزاح موعد الاستحقاق السنوي عند تأخر المعالجة الإدارية.
     */
    public function applyRenewal(Membership $membership, ?int $reviewerId = null): Membership
    {
        $previous = $membership->contractor
            ->memberships()
            ->where('id', '!=', $membership->id)
            ->whereNotNull('expires_at')
            ->orderByDesc('expires_at')
            ->first();

        $anchor = $previous?->expires_at ?? $membership->starts_at ?? now();

        $membership->update([
            'status'      => 'active',
            'starts_at'   => $anchor,
            'expires_at'  => $anchor->copy()->addYear(),
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ]);

        $contractor = $membership->contractor;
        if ($contractor && in_array($contractor->status, ['pending', 'expired'], true)) {
            $contractor->update(['status' => 'active']);
        }

        return $membership->fresh();
    }

    /**
     * يُستدعى من PaymentController::confirm() عند تأكيد دفعة رسوم عضوية (type=membership_fee):
     * يجدّد العضوية المرتبطة إن وُجدت، أو ينشئ عضوية جديدة تلقائياً ويربطها بالدفعة.
     * يعيد null إن لم تكن الدفعة من نوع رسوم عضوية أصلاً.
     */
    public function renewFromPayment(Payment $payment, ?int $reviewerId = null): ?Membership
    {
        if ($payment->type !== 'membership_fee') {
            return null;
        }

        $membership = $payment->membership;

        if (! $membership) {
            $contractor = $payment->contractor;
            $hasPrior   = $contractor->memberships()->exists();

            $membership = $contractor->memberships()->create([
                'type'   => $hasPrior ? 'renewal' : 'new',
                'status' => 'pending',
                'amount' => $payment->amount,
            ]);

            $payment->update(['membership_id' => $membership->id]);
        }

        return $this->applyRenewal($membership, $reviewerId);
    }
}
