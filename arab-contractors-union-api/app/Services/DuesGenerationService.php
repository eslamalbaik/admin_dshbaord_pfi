<?php

namespace App\Services;

use App\Models\Contractor;
use App\Models\ContractorDue;

class DuesGenerationService
{
    public function __construct(
        private MembershipFeeCalculator $feeCalculator,
        private DuesDiscountService $discountService
    ) {}

    /**
     * معاينة رسوم العضوية
     */
    public function calculateFee(Contractor $contractor, int $year, ?string $discountType, ?float $discountValue): array
    {
        $breakdown = $this->feeCalculator->calculate($contractor, $year);

        if ($breakdown['unresolvable']) {
            throw new \Exception('تعذّر احتساب الرسوم — يوجد تخصص بدرجة غير موحَّدة على هذا المقاول. شغّل أمر contractor:normalize-specialty-grades أولاً.');
        }

        $total = $breakdown['total_before_discount_jod'];
        $final = $this->discountService->previewDiscount($total, $discountType, $discountValue);

        return [
            'breakdown'                 => $breakdown,
            'total_before_discount_jod' => $total,
            'discount_amount_jod'       => round($total - $final, 2),
            'total_after_discount_jod'  => $final,
        ];
    }

    /**
     * توليد ذمة رسوم عضوية لمقاول واحد
     */
    public function generateFee(Contractor $contractor, int $year, array $discountData, bool $force, int $authId): array
    {
        $breakdown = $this->feeCalculator->calculate($contractor, $year);

        if ($breakdown['unresolvable']) {
            throw new \Exception('تعذّر احتساب الرسوم — يوجد تخصص بدرجة غير موحَّدة على هذا المقاول. شغّل أمر contractor:normalize-specialty-grades أولاً.');
        }

        $existingFeeEngine = ContractorDue::where('contractor_id', $contractor->id)
            ->where('year', $year)
            ->where('source', 'fee_engine')
            ->first();

        if ($existingFeeEngine && ! $force) {
            throw new \Exception('يوجد بالفعل ذمة رسوم مُولَّدة لهذا المقاول عن هذه السنة.', 409);
        }

        $total = $breakdown['total_before_discount_jod'];
        $final = $this->discountService->previewDiscount($total, $discountData['discount_type'] ?? null, $discountData['discount_value'] ?? null);

        $attributes = [
            'contractor_id'        => $contractor->id,
            'year'                 => $year,
            'description'          => "رسوم اشتراك سنة {$year} (محرّك الاحتساب الآلي)",
            'amount_jod'           => $final,
            'original_amount_jod'  => $total !== $final ? $total : null,
            'discount_type'        => $discountData['discount_type'] ?? null,
            'discount_value'       => $discountData['discount_value'] ?? null,
            'discount_amount_jod'  => $total !== $final ? round($total - $final, 2) : null,
            'discount_reason'      => $discountData['discount_reason'] ?? null,
            'discount_by'          => isset($discountData['discount_type']) ? $authId : null,
            'fee_breakdown'        => $breakdown,
            'source'               => 'fee_engine',
        ];

        if ($existingFeeEngine) {
            $existingFeeEngine->update($attributes);
            $existingFeeEngine->applyPayment(0);
            $due = $existingFeeEngine->fresh(['contractor:id,name,membership_number']);
        } else {
            $attributes['created_by'] = $authId;
            $due = ContractorDue::create($attributes);
            $due->update(['reference_number' => ContractorDue::generateReferenceNumber($due)]);
            $due = $due->fresh(['contractor:id,name,membership_number']);
        }

        $otherExisting = ContractorDue::where('contractor_id', $contractor->id)
            ->where('year', $year)
            ->where('source', '!=', 'fee_engine')
            ->exists();

        return [
            'due'     => $due,
            'warning' => $otherExisting ? 'يوجد بالفعل ذمة أخرى (غير محرّك الاحتساب) لنفس المقاول والسنة — تحقّق من عدم ازدواج الرسوم.' : null,
        ];
    }

    /**
     * توليد الرسوم بشكل جماعي
     */
    public function generateFeeBulk(array $contractorIds, int $year, bool $dryRun, int $authId): array
    {
        $query = Contractor::query()->whereNotNull('specialties');
        if (! empty($contractorIds)) {
            $query->whereIn('id', $contractorIds);
        }

        $wouldCreate = [];
        $wouldSkipExisting = [];
        $unresolvable = [];
        $created = 0;

        $query->chunkById(100, function ($contractors) use ($year, $dryRun, $authId, &$wouldCreate, &$wouldSkipExisting, &$unresolvable, &$created) {
            foreach ($contractors as $contractor) {
                $exists = ContractorDue::where('contractor_id', $contractor->id)
                    ->where('year', $year)
                    ->where('source', 'fee_engine')
                    ->exists();

                if ($exists) {
                    $wouldSkipExisting[] = ['contractor_id' => $contractor->id, 'name' => $contractor->name];
                    continue;
                }

                $breakdown = $this->feeCalculator->calculate($contractor, $year);

                if ($breakdown['unresolvable']) {
                    $unresolvable[] = ['contractor_id' => $contractor->id, 'name' => $contractor->name];
                    continue;
                }

                if ($dryRun) {
                    $wouldCreate[] = ['contractor_id' => $contractor->id, 'name' => $contractor->name, 'total_jod' => $breakdown['total_before_discount_jod']];
                    continue;
                }

                $due = ContractorDue::create([
                    'contractor_id' => $contractor->id,
                    'year'          => $year,
                    'description'   => "رسوم اشتراك سنة {$year} (محرّك الاحتساب الآلي)",
                    'amount_jod'    => $breakdown['total_before_discount_jod'],
                    'fee_breakdown' => $breakdown,
                    'source'        => 'fee_engine',
                    'created_by'    => $authId,
                ]);
                $due->update(['reference_number' => ContractorDue::generateReferenceNumber($due)]);
                $wouldCreate[] = ['contractor_id' => $contractor->id, 'name' => $contractor->name, 'total_jod' => $breakdown['total_before_discount_jod']];
                $created++;
            }
        });

        return [
            'would_create'        => $wouldCreate,
            'would_skip_existing' => $wouldSkipExisting,
            'unresolvable'        => $unresolvable,
            'created_count'       => $created,
        ];
    }
}
