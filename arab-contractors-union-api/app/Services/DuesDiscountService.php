<?php

namespace App\Services;

use App\Models\Contractor;
use App\Models\ContractorDue;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DuesDiscountService
{
    /**
     * معاينة الخصم (لا يحفظ في قاعدة البيانات)
     */
    public function previewDiscount(float $total, ?string $type, ?float $value): float
    {
        if (! $type || ! $value) {
            return $total;
        }

        $discounted = $type === 'percent' ? $total * (1 - $value / 100) : $total - $value;

        return round(max(0, $discounted), 2);
    }

    /**
     * تطبيق الخصم الجماعي على ذمم متعددة.
     *
     * مود «معايير» يُلزم وجود معيار واحد على الأقل (يُفرَض في ApplyDiscountBulkRequest):
     * كائن معايير فارغ كان يمرّ بالتحقق فيتخطّى كل شرط أدناه ويطابق **كل ذمة في النظام** —
     * أي أن خصم 100% بلا معايير كان يصفّر ذمم كل المقاولين دفعة واحدة (TASK-17 #10).
     */
    public function applyBulk(array $criteria, string $mode, array $discountData, bool $dryRun, int $authId): array
    {
        $query = ContractorDue::query();

        if ($mode === 'ids') {
            $query->whereIn('id', $criteria['ids']);
        } else {
            if (! empty($criteria['year']))           $query->where('year', $criteria['year']);
            if (! empty($criteria['status']))         $query->where('status', $criteria['status']);
            if (! empty($criteria['source']))         $query->where('source', $criteria['source']);
            // بدون هذا الشرط كان «خصم جماعي بمعايير» يطابق ذمم كل المقاولين بنفس السنة،
            // لا ذمم المقاولين المقصودين — وهو سبب ظهور ذمّتين كستّ.
            if (! empty($criteria['contractor_ids'])) $query->whereIn('contractor_id', $criteria['contractor_ids']);
        }

        // الذمم المسدَّدة من ضمن المطابقة: الخصم بينطبق عليها والفرق بيرجع رصيد للمقاول.
        // استثناؤها كان يخلّي خصم 20% على 3 ذمم × 100 (وحدة منها مسدَّدة) يطلع 40 بدل 60.

        $dues = $query->get();

        $projections = $dues->map(fn ($due) => [
            'due'        => $due,
            'projection' => $due->projectDiscount($discountData['discount_type'], (float) $discountData['discount_value']),
        ]);

        $blocked = $projections->filter(fn ($p) => $p['projection']['blocked_reason'] !== null);
        $usable  = $projections->reject(fn ($p) => $p['projection']['blocked_reason'] !== null);

        $skipped = $blocked->map(fn ($p) => [
            'due_id' => $p['due']->id,
            'reason' => $p['projection']['blocked_reason'],
        ])->values()->all();

        if ($dryRun) {
            return [
                'matched_count'    => $dues->count(),
                // العدد القابل للخصم فعلاً — المعاينة كانت تعدّ الذمم التي سيرفضها التطبيق
                // وتضيف أثرها الكامل للإجمالي، فيظهر رقم أكبر من الواقع.
                'applicable_count' => $usable->count(),
                'contractors_count' => $dues->pluck('contractor_id')->unique()->count(),
                'total_discount_impact_jod' => round($usable->sum(fn ($p) => $p['projection']['impact']), 2),
                'refund_to_credit_jod' => round($usable->sum(fn ($p) => $p['projection']['refund_to_credit']), 2),
                'skipped'          => $skipped,
                'is_dry_run'       => true,
            ];
        }

        $applied = 0;
        $refunded = 0.0;

        DB::transaction(function () use ($usable, $discountData, $authId, &$applied, &$refunded, &$skipped) {
            foreach ($usable as $p) {
                try {
                    $p['due']->applyDiscount(
                        $discountData['discount_type'],
                        (float) $discountData['discount_value'],
                        $discountData['discount_reason'] ?? null,
                        $authId
                    );
                    $applied++;
                    $refunded += $p['projection']['refund_to_credit'];
                } catch (\InvalidArgumentException $e) {
                    $skipped[] = ['due_id' => $p['due']->id, 'reason' => $e->getMessage()];
                }
            }

            // الرصيد اللي رجع من ذمة مسدَّدة بينصرف على ذمم المقاول المفتوحة (بعد الخصم)
            if ($refunded > 0) {
                $by = User::find($authId);
                Contractor::whereIn('id', $usable->pluck('due.contractor_id')->unique())->get()
                    ->each(fn ($contractor) => app(ContractorCreditService::class)->applyAvailableCredit($contractor, $by));
            }
        });

        return [
            'matched_count'     => $dues->count(),
            'applicable_count'  => $usable->count(),
            'contractors_count' => $dues->pluck('contractor_id')->unique()->count(),
            'applied_count'     => $applied,
            'refund_to_credit_jod' => round($refunded, 2),
            'skipped'           => $skipped,
            'is_dry_run'        => false,
        ];
    }
}
