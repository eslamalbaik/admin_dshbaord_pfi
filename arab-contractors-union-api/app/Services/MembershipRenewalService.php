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
     * كل العضويات سنوية على السنة الميلادية وتنتهي 31/12 (قرار الإدارة 2026-10-05): من يدفع
     * يوم 30/12 تنتهي عضويته بعد يوم واحد، فالانتهاء لا يُحسب من تاريخ الدفع ولا من التسجيل.
     *
     * السنة المغطّاة = السنة الحالية، إلا إن كانت عضوية سابقة تغطيها أصلاً (دفع مسبق للسنة
     * القادمة) فتصير السنة التالية لآخر سنة مغطّاة — حتى لا تُدفع نفس السنة مرتين.
     */
    public function applyRenewal(Membership $membership, ?int $reviewerId = null): Membership
    {
        $previous = $membership->contractor
            ->memberships()
            ->where('id', '!=', $membership->id)
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->orderByDesc('expires_at')
            ->first();

        $coveredYear = max(now()->year, ($previous?->expires_at?->year ?? 0) + 1);

        // داخل السنة الحالية تبدأ من يوم المعالجة؛ الدفع المسبق لسنة قادمة يبدأ من 1/1 تبعها
        $startsAt = $coveredYear === now()->year
            ? now()->startOfDay()
            : \Carbon\Carbon::create($coveredYear, 1, 1)->startOfDay();

        $membership->update([
            'status'      => 'active',
            'starts_at'   => $startsAt,
            'expires_at'  => \Carbon\Carbon::create($coveredYear, 12, 31)->startOfDay(),
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
        $contractor = $payment->contractor;

        // لقطة قبل التجديد، عشان ترجيع الدفعة (revertFromPayment) يرجّع كل شي زي ما كان
        $snapshot = [
            'created'           => ! $membership,
            'status'            => $membership?->status,
            'starts_at'         => $membership?->starts_at?->toDateString(),
            'expires_at'        => $membership?->expires_at?->toDateString(),
            'reviewed_by'       => $membership?->reviewed_by,
            'reviewed_at'       => $membership?->reviewed_at?->toDateTimeString(),
            'contractor_status' => $contractor?->status,
        ];

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

        $payment->update(['membership_snapshot' => $snapshot]);

        return $this->applyRenewal($membership, $reviewerId);
    }

    /**
     * عكس renewFromPayment() لما دفعة رسوم عضوية مؤكَّدة ترجع لقيد المراجعة أو تنرفض:
     * - عضوية أنشأتها الدفعة ← بتصير مرفوضة (ما بتغطي أي سنة).
     * - طلب تجديد كان موجود ← بيرجع لحالته وتواريخه قبل التأكيد.
     * - حالة المقاول بترجع لو التجديد هو اللي فعّله.
     * دفعات أُكِّدت قبل اللقطة: العضوية بتصير مرفوضة وحالة المقاول ما بتتغيّر.
     */
    public function revertFromPayment(Payment $payment): void
    {
        if ($payment->type !== 'membership_fee' || ! $payment->membership) {
            return;
        }

        $membership = $payment->membership;
        $snapshot   = $payment->membership_snapshot;

        if ($snapshot && ! $snapshot['created']) {
            $membership->update([
                'status'      => $snapshot['status'],
                'starts_at'   => $snapshot['starts_at'],
                'expires_at'  => $snapshot['expires_at'],
                'reviewed_by' => $snapshot['reviewed_by'],
                'reviewed_at' => $snapshot['reviewed_at'],
            ]);
        } else {
            $membership->update([
                'status'     => 'rejected',
                'starts_at'  => null,
                'expires_at' => null,
            ]);
        }

        $contractor = $payment->contractor;
        if ($snapshot && $contractor && $contractor->status === 'active'
            && in_array($snapshot['contractor_status'], ['pending', 'expired'], true)) {
            $contractor->update(['status' => $snapshot['contractor_status']]);
        }

        $payment->update(['membership_snapshot' => null]);
    }
}
