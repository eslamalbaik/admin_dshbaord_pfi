<?php

namespace App\Services;

use App\Models\ContractorDue;

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
     * تطبيق الخصم الجماعي على ذمم متعددة
     */
    public function applyBulk(array $criteria, string $mode, array $discountData, bool $dryRun, int $authId): array
    {
        $query = ContractorDue::query();

        if ($mode === 'ids') {
            $query->whereIn('id', $criteria['ids']);
        } else {
            if (! empty($criteria['year']))   $query->where('year', $criteria['year']);
            if (! empty($criteria['status'])) $query->where('status', $criteria['status']);
            if (! empty($criteria['source'])) $query->where('source', $criteria['source']);
        }

        $dues = $query->get();

        if ($dryRun) {
            $impact = $dues->sum(function ($due) use ($discountData) {
                $original = (float) ($due->original_amount_jod ?? $due->amount_jod);
                $newAmount = $discountData['discount_type'] === 'percent'
                    ? $original * (1 - $discountData['discount_value'] / 100)
                    : $original - $discountData['discount_value'];
                return round($original - max(0, $newAmount), 2);
            });

            return [
                'matched_count'             => $dues->count(),
                'total_discount_impact_jod' => round($impact, 2),
                'is_dry_run'                => true,
            ];
        }

        $applied = 0;
        $skipped = [];

        foreach ($dues as $due) {
            try {
                $due->applyDiscount(
                    $discountData['discount_type'],
                    $discountData['discount_value'],
                    $discountData['discount_reason'] ?? null,
                    $authId
                );
                $applied++;
            } catch (\InvalidArgumentException $e) {
                $skipped[] = ['due_id' => $due->id, 'reason' => $e->getMessage()];
            }
        }

        return [
            'matched_count' => $dues->count(),
            'applied_count' => $applied,
            'skipped'       => $skipped,
            'is_dry_run'    => false,
        ];
    }
}
