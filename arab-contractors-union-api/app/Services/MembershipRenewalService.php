<?php

namespace App\Services;

use App\Models\ContractorDue;
use App\Models\Membership;
use App\Models\Payment;

/**
 * منطق تجديد العضوية الموحّد — يُستخدم من موافقة الأدمن اليدوية (MembershipController::approve)
 * ومن تأكيد الدفعة (PaymentController::confirm) على حد سواء، لضمان نفس قاعدة "الموعد الثابت".
 */
class MembershipRenewalService
{
    /**
     * كل العضويات سنوية على السنة الميلادية وتنتهي 31/12 من سنة الدفع (قرار الإدارة 2026-10-05):
     * من يدفع يوم 30/12 تنتهي عضويته بعد يوم واحد، فالانتهاء لا يُحسب من تاريخ الدفع ولا من التسجيل.
     *
     * السنة المغطّاة = سنة المعالجة دائماً، حتى لو في عضوية فعّالة تغطيها أصلاً: دفعة ثانية أو
     * مبلغ كبير ما بيمدّ العضوية لسنة قادمة (eslam 2026-10-07، حساب 9541_g طلع 2027-12-31).
     * عضوية السنة الجاية بتنعمل لما تندفع رسومها السنوية بسنتها.
     */
    public function applyRenewal(Membership $membership, ?int $reviewerId = null): Membership
    {
        $coveredYear = now()->year;
        $startsAt    = now()->startOfDay();

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
     * رسوم اشتراك سنة معيّنة انسدّت كاملة من رصيد المقاول (مثلاً لما تتولّد رسوم 1/1) ← عضوية
     * فعّالة بتنتهي 31/12 من سنة الذمة. ما بتنعمل وحدة ثانية لو في عضوية فعّالة بتغطي السنة أصلاً.
     * العضوية بتنعلّم بالذمة، عشان ترجيع الدفعة يلغيها (deactivateForDue).
     */
    public function activateForDue(ContractorDue $due, ?int $reviewerId = null): ?Membership
    {
        $contractor = $due->contractor;
        $year       = (int) $due->year;

        $covered = $contractor->memberships()
            ->where('status', 'active')
            ->whereYear('expires_at', $year)
            ->exists();

        if ($covered) {
            return null;
        }

        $startsAt = $year === now()->year
            ? now()->startOfDay()
            : \Carbon\Carbon::create($year, 1, 1)->startOfDay();

        $membership = $contractor->memberships()->create([
            'type'        => $contractor->memberships()->exists() ? 'renewal' : 'new',
            'status'      => 'active',
            'amount'      => $due->amount_jod,
            'starts_at'   => $startsAt,
            'expires_at'  => \Carbon\Carbon::create($year, 12, 31)->startOfDay(),
            'notes'       => self::creditDueMarker($due),
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ]);

        if (in_array($contractor->status, ['pending', 'expired'], true)) {
            $contractor->update(['status' => 'active']);
        }

        return $membership;
    }

    /** الذمة رجعت مش مسدّدة (ترجيع الدفعة اللي سدّتها) ← العضوية اللي فعّلها الرصيد بتنرفض */
    public function deactivateForDue(ContractorDue $due): void
    {
        if ($due->status === 'paid') {
            return;
        }

        $due->contractor->memberships()
            ->where('status', 'active')
            ->where('notes', self::creditDueMarker($due))
            ->update(['status' => 'rejected']);
    }

    private static function creditDueMarker(ContractorDue $due): string
    {
        return "تفعيل تلقائي من رصيد المقاول — ذمة #{$due->id}";
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
            // عضوية كانت مرفوضة قبل هالتأكيد (من إرجاع سابق لنفس الدفعة) ما بتضل مربوطة فيها
            if ($snapshot['status'] === 'rejected') {
                $payment->membership_id = null;
            }
        } else {
            $membership->update([
                'status'     => 'rejected',
                'starts_at'  => null,
                'expires_at' => null,
            ]);
            // العضوية انعملت تلقائياً من هالدفعة، فبنفكّ الربط: لو رجعت تتأكد بتتقرر من جديد
            // (سداد ذمم لو عليه ذمم وقتها) بدل ما ترجع تفعّل العضوية المرفوضة نفسها
            $payment->membership_id = null;
        }

        $contractor = $payment->contractor;
        if ($snapshot && $contractor && $contractor->status === 'active'
            && in_array($snapshot['contractor_status'], ['pending', 'expired'], true)) {
            $contractor->update(['status' => $snapshot['contractor_status']]);
        }

        $payment->update(['membership_snapshot' => null]);
    }
}
