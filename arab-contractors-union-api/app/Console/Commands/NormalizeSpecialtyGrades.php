<?php

namespace App\Console\Commands;

use App\Models\Contractor;
use App\Support\ContractorLookups;
use Illuminate\Console\Command;

class NormalizeSpecialtyGrades extends Command
{
    protected $signature = 'contractor:normalize-specialty-grades
                            {--fix : كتابة القيم الموحَّدة فعلياً (بدونها العرض تقرير فقط dry-run)}';

    protected $description = 'توحيد قيم specialties[].classification إلى الرموز الستة المعتمدة (المادة 37) بدل خليط الحروف والنصوص القديم';

    /** خريطة القيم القديمة (حرف أو نص كامل) إلى الرمز الموحَّد — لا تشمل 'أ' لأنها غامضة لمجالي 20/30 */
    private const MAP = [
        'ب'       => 'ثانية',
        'الثانية' => 'ثانية',
        'ج'       => 'ثالثة',
        'الثالثة' => 'ثالثة',
        'د'       => 'رابعة',
        'الرابعة' => 'رابعة',
        'هـ'      => 'خامسة',
        'الخامسة' => 'خامسة',
        'الأولى أ' => 'اولى أ',
        'الأولى ب' => 'اولى ب',
    ];

    public function handle(): int
    {
        $canonical = array_keys(ContractorLookups::SPECIALTY_GRADE_LABELS);
        $fix       = (bool) $this->option('fix');

        $normalizedCount = 0;
        $ambiguous       = [];
        $unrecognized    = [];

        Contractor::withTrashed()
            ->whereNotNull('specialties')
            ->chunkById(200, function ($contractors) use (&$normalizedCount, &$ambiguous, &$unrecognized, $canonical, $fix) {
                foreach ($contractors as $contractor) {
                    $specialties = $contractor->specialties ?? [];
                    if (! is_array($specialties) || $specialties === []) {
                        continue;
                    }

                    $changed = false;

                    foreach ($specialties as $i => $spec) {
                        $raw = $spec['classification'] ?? null;
                        if ($raw === null || in_array($raw, $canonical, true)) {
                            continue; // مفقود أو موحَّد أصلاً
                        }

                        $fieldId = $spec['field_lk_type'] ?? null;

                        if ($raw === 'أ') {
                            if (in_array($fieldId, ContractorLookups::TOP_TIER_FIELDS, true)) {
                                $ambiguous[] = [$contractor->id, $contractor->membership_number, $fieldId, $raw];
                                continue;
                            }
                            $specialties[$i]['classification'] = 'اولى ب';
                            $changed = true;
                            $normalizedCount++;
                            continue;
                        }

                        if (isset(self::MAP[$raw])) {
                            $specialties[$i]['classification'] = self::MAP[$raw];
                            $changed = true;
                            $normalizedCount++;
                            continue;
                        }

                        $unrecognized[] = [$contractor->id, $contractor->membership_number, $fieldId, $raw];
                    }

                    if ($changed && $fix) {
                        $contractor->specialties = $specialties;
                        $contractor->save();
                    }
                }
            });

        $this->info("عدد قيم التصنيف الموحَّدة: {$normalizedCount}" . ($fix ? '' : ' (dry-run — لم يُكتب شيء بعد)'));

        if ($ambiguous) {
            $this->warn("قيم غامضة تحتاج مراجعة يدوية ('أ' على مجال طرق/ابنية، لا تُحل تلقائياً): " . count($ambiguous));
            $this->table(['ID', 'رقم العضوية', 'المجال', 'القيمة'], $ambiguous);
        }

        if ($unrecognized) {
            $this->warn('قيم غير معروفة (تُركت كما هي): ' . count($unrecognized));
            $this->table(['ID', 'رقم العضوية', 'المجال', 'القيمة'], $unrecognized);
        }

        if (! $fix) {
            $this->line('هذا عرض فقط (dry-run). لكتابة القيم الموحَّدة فعلياً شغّل الأمر مع --fix');
        }

        return self::SUCCESS;
    }
}
