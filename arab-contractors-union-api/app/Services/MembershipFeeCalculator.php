<?php

namespace App\Services;

use App\Models\Contractor;
use App\Models\GradeFee;
use App\Support\ContractorLookups;

/**
 * محرّك احتساب رسوم العضوية السنوية (المادة 37): لكل مجال تُعتمَد أعلى درجة فيه فقط
 * (باقي تخصصات نفس المجال تُهمَل تماماً)، ثم أعلى مجال بين المجالات يُحتسَب 100%
 * والبقية 50% من رسم الاشتراك السنوي لدرجتها.
 */
class MembershipFeeCalculator
{
    /**
     * @return array{
     *   unresolvable: bool,
     *   fields: array,
     *   total_before_discount_jod: float,
     *   calculated_at: string,
     * }
     */
    public function calculate(Contractor $contractor, int $year): array
    {
        $fieldsTree = ContractorLookups::buildFieldsTree($contractor->specialties);
        $gradeFees  = GradeFee::all()->keyBy('grade_code');
        $isNewRegistrationYear = $this->isNewRegistrationYear($contractor, $year);

        $unresolvable   = false;
        $fieldBreakdown = [];

        foreach ($fieldsTree as $field) {
            $specializations = $field['specializations'];

            // الدرجة الأعلى (أقل grade_level) داخل نفس المجال — الباقي يُهمَل
            $resolved = array_values(array_filter($specializations, fn ($s) => $s['grade_level'] !== null));
            $hasUnresolved = count($resolved) < count($specializations);

            if ($resolved === []) {
                $unresolvable = true;
                $fieldBreakdown[] = [
                    'field_id'   => $field['field_id'],
                    'field_name' => $field['field_name'],
                    'unresolvable' => true,
                ];
                continue;
            }

            usort($resolved, fn ($a, $b) => $a['grade_level'] <=> $b['grade_level']);
            $counted    = $resolved[0];
            $disregarded = array_slice($resolved, 1);

            if ($hasUnresolved) {
                $unresolvable = true;
            }

            $gradeFee = $gradeFees->get($counted['grade']);
            if (! $gradeFee) {
                $unresolvable = true;
                $fieldBreakdown[] = [
                    'field_id'   => $field['field_id'],
                    'field_name' => $field['field_name'],
                    'unresolvable' => true,
                ];
                continue;
            }

            $fieldBreakdown[] = [
                'field_id'             => $field['field_id'],
                'field_name'           => $field['field_name'],
                'counted_specialty'    => $counted,
                'disregarded_specialties' => $disregarded,
                'grade_level'          => $counted['grade_level'],
                'annual_fee_jod'       => (float) $gradeFee->annual_fee_jod,
                'registration_fee_jod' => (float) $gradeFee->registration_fee_jod,
            ];
        }

        if ($unresolvable) {
            return [
                'unresolvable'              => true,
                'fields'                    => $fieldBreakdown,
                'total_before_discount_jod' => 0.0,
                'calculated_at'             => now()->toISOString(),
            ];
        }

        if ($fieldBreakdown === []) {
            return [
                'unresolvable'              => false,
                'fields'                    => [],
                'total_before_discount_jod' => 0.0,
                'calculated_at'             => now()->toISOString(),
            ];
        }

        // أعلى مجال (أقل grade_level بين المجالات) يُحتسَب 100%، الباقي 50%
        $highestLevel = min(array_column($fieldBreakdown, 'grade_level'));
        $highestPicked = false;
        $total = 0.0;

        foreach ($fieldBreakdown as &$row) {
            $isHighest = ! $highestPicked && $row['grade_level'] === $highestLevel;
            if ($isHighest) {
                $highestPicked = true;
            }

            $row['is_overall_highest'] = $isHighest;

            if ($isHighest && $isNewRegistrationYear) {
                // أول سنة انتساب (المادة 37): رسم التسجيل يحل محل الرسم السنوي للمجال الأعلى فقط
                $row['fee_type']     = 'registration';
                $row['rate_percent'] = 100;
                $row['amount_jod']   = round($row['registration_fee_jod'], 2);
            } else {
                $row['fee_type']     = 'annual';
                $row['rate_percent'] = $isHighest ? 100 : 50;
                $row['amount_jod']   = round($row['annual_fee_jod'] * ($row['rate_percent'] / 100), 2);
            }

            $total += $row['amount_jod'];
        }
        unset($row);

        return [
            'unresolvable'              => false,
            'year'                      => $year,
            'is_new_registration_year'  => $isNewRegistrationYear,
            'fields'                    => $fieldBreakdown,
            'total_before_discount_jod' => round($total, 2),
            'calculated_at'             => now()->toISOString(),
        ];
    }

    /**
     * أول سنة انتساب لمقاول (لا تجديد) — تُحدَّد بوجود عضوية type=new تبدأ بنفس السنة المطلوب
     * احتساب رسومها، فيُطبَّق رسم التسجيل بدل الرسم السنوي على المجال الأعلى فقط (المادة 37).
     */
    private function isNewRegistrationYear(Contractor $contractor, int $year): bool
    {
        return $contractor->memberships()
            ->where('type', 'new')
            ->get()
            ->contains(fn ($membership) => ($membership->starts_at ?? $membership->created_at)?->year === $year);
    }
}
