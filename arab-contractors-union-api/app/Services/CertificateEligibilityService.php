<?php

namespace App\Services;

use App\Models\Contractor;
use App\Support\ContractorRequirements;

class CertificateEligibilityService
{
    public function __construct(
        private ContractorFinancialService $financialService
    ) {}
    /**
     * حساب جميع العوائق التي تمنع المقاول من طلب الشهادة.
     */
    public function getIssues(Contractor $contractor): array
    {
        return ContractorRequirements::issues($contractor);
    }

    /**
     * حساب العوائق المحظّرة للطلب.
     * لشهادة العضوية، نستثني 'unpaid_dues' لأن لها استثناء نسبة الـ 95%.
     */
    public function getBlockingIssues(Contractor $contractor, string $type = 'membership'): array
    {
        $issues = $this->getIssues($contractor);

        if ($type === 'membership') {
            return array_values(array_filter($issues, fn ($i) => $i['type'] !== 'unpaid_dues'));
        }

        return $issues;
    }

    /**
     * التحقق الشامل من الأهلية وإرجاع سبب المنع (إن وجد)
     */
    public function checkEligibility(Contractor $contractor, string $type): array
    {
        if (! $contractor->profile_data_complete) {
            return [
                'eligible' => false,
                'reason' => 'يجب إكمال بيانات الملف الشخصي قبل تقديم طلب شهادة.',
                'code' => 'profile_incomplete',
                'context' => ['missing_profile_fields' => $contractor->missing_profile_fields],
            ];
        }

        $blockingIssues = $this->getBlockingIssues($contractor, $type);

        if (count($blockingIssues) > 0) {
            return [
                'eligible' => false,
                'reason' => 'لا يمكن تقديم الطلب قبل تسوية المتطلبات المستحقّة.',
                'code' => 'requirements_pending',
                'context' => ['issues' => $blockingIssues],
            ];
        }

        if ($type === 'membership' && ! $this->financialService->isEligibleForMembershipCertificate($contractor)) {
            // المقاول الذي دفع رسومه من التطبيق تبقى دفعته pending حتى يعتمدها المحاسب،
            // ولا يتحرّك paid_jod قبل ذلك — فكان الطلب يُرفَض ولا يُنشأ صف إطلاقاً، وتظهر
            // اللوحة فارغة لمن دفع فعلاً (TASK-17 #5). يُسمح بالتقديم الآن ويُربَط الطلب
            // بالدفعة، على أن تبقى الموافقة والإصدار موقوفَين حتى تأكيدها.
            $pendingPayment = $this->pendingMembershipFeePayment($contractor);

            if ($pendingPayment) {
                return [
                    'eligible'        => true,
                    'pending_payment' => $pendingPayment,
                ];
            }

            return [
                'eligible' => false,
                'reason' => "يتبقى لك سداد {$this->financialService->remainingToReach95PercentJod($contractor)} دينار للوصول إلى حد الـ 95% واستخراج شهادتك تلقائياً.",
                'code' => 'dues_below_threshold',
                'context' => [
                    'paid_percentage'   => $this->financialService->currentYearDuesPaidPercentage($contractor),
                    'outstanding_jod'   => $this->financialService->outstandingDuesTotal($contractor),
                    'remaining_to_95_jod' => $this->financialService->remainingToReach95PercentJod($contractor),
                    'current_year'      => now()->year,
                    'remaining_dues'   => $contractor->dues()->outstanding()->get()->map(fn ($d) => [
                        'id'           => $d->id,
                        'description'  => $d->description,
                        'year'         => $d->year,
                        'amount_jod'   => $d->amount_jod,
                        'remaining_jod' => $d->remaining_jod,
                        'status'       => $d->status,
                    ])->values(),
                ],
            ];
        }

        return ['eligible' => true];
    }

    /**
     * أحدث دفعة رسوم عضوية قدّمها المقاول وما زالت قيد تأكيد المحاسب.
     *
     * الشرط مقصود ضيّقاً: وجودها لا يُسقط أي عائق آخر (ملف ناقص، حساب موقوف، غرامات) —
     * تلك تبقى مانعة للتقديم. ولا يُسقط الحدّ المالي نفسه، بل يُبدّل **توقيته**: الطلب يُرى
     * ويُراجَع بانتظار الاعتماد، والموافقة والإصدار موقوفان حتى يتحقّق المال فعلاً.
     *
     * لا يُشترط أن تغطّي الدفعة الفجوة حتى حدّ الـ95%: amount_jod لا يُحتسب إلا لحظة
     * الاعتماد (PaymentConfirmationService)، فالدفعة المعلّقة لا تحمل قيمة بالدينار بعد،
     * وأي تقدير لها هنا سيكون تخميناً بسعر صرف قد لا يوجد. والحساب المالي الحقيقي يُعاد
     * تطبيقه عند الاعتماد على أي حال، وهو ما يحكم الإصدار.
     */
    public function pendingMembershipFeePayment(Contractor $contractor): ?\App\Models\Payment
    {
        return $contractor->payments()
            ->where('type', 'membership_fee')
            ->where('status', 'pending')
            ->latest()
            ->first();
    }
}
