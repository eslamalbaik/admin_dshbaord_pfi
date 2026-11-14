<?php

namespace App\Support;

use App\Models\ContractorField;
use App\Models\ContractorGrade;
use App\Models\ContractorSpecialization;
use Illuminate\Support\Facades\Cache;

/**
 * جداول المرجعية للمجالات والاختصاصات والدرجات (مصدر واحد — REQ-08، وREQ-01 #7).
 * تُقرأ من الجداول contractor_fields / contractor_specializations / contractor_grades
 * القابلة للإدارة من لوحة الأدمن، مع رجوع (fallback) للقيم الثابتة أدناه فقط لو الجداول
 * فارغة (بيئة لم تُطبَّق عليها seed migration بعد) — لا تُستخدم الثوابت مباشرة بأي مكان آخر
 * بالكود حتى لا يتفرّع مصدر الحقيقة بين مكانين (كل الاستهلاك يمر بميثودز هذا الكلاس).
 */
class ContractorLookups
{
    private const CACHE_TTL = 3600;

    private const DEFAULT_FIELDS = [
        10 => 'غير محدد',
        20 => 'طرق',
        30 => 'ابنية',
        40 => 'كهروميكانيك',
        50 => 'الميــاه/المجــارى',
        60 => 'أشغال عامه',
    ];

    private const DEFAULT_SPECIALIZATIONS = [
        10  => 'غير محدد',
        20  => 'الطرق',
        30  => 'خلطات اسفلتيه',
        40  => 'خرسانه جسور وعبارات',
        50  => 'اشغال ترابيه',
        60  => 'الأبنية',
        70  => 'خرسانه مصنعه',
        80  => 'منشأت معدنية',
        90  => 'أبنية جاهزه بريفاف',
        100 => 'صيانة الابنيه',
        110 => 'كهروميكانيك',
        120 => 'صيانة كهروميكانيك',
        130 => 'ميكانيك',
        140 => 'كـهرباء',
        150 => 'الكترونيات',
        160 => 'المياه والمجاري',
        170 => 'محطات التنقيه',
        180 => 'الري والصرف',
        190 => 'حفريات وتعدين',
        200 => 'اشغال عامه',
        210 => 'سكك حديدية',
        220 => 'حفر آبار',
    ];

    private const DEFAULT_FIELD_SPECIALIZATIONS = [
        20 => [20, 30, 40, 50, 210],
        30 => [60, 70, 80, 90, 100],
        40 => [110, 120, 130, 140, 150],
        50 => [160, 170, 180, 220],
        60 => [190, 200],
    ];

    private const DEFAULT_GRADE_LEVELS = [
        'اولى أ' => 1,
        'اولى ب' => 2,
        'ثانية'  => 3,
        'ثالثة'  => 4,
        'رابعة'  => 5,
        'خامسة'  => 6,
    ];

    private const DEFAULT_SPECIALTY_GRADE_LABELS = [
        'اولى أ' => 'الدرجة الأولى (أ)',
        'اولى ب' => 'الدرجة الأولى (ب)',
        'ثانية'  => 'الدرجة الثانية',
        'ثالثة'  => 'الدرجة الثالثة',
        'رابعة'  => 'الدرجة الرابعة',
        'خامسة'  => 'الدرجة الخامسة',
    ];

    private const DEFAULT_TOP_TIER_FIELDS = [20, 30];

    // ترجمة شهادة العضوية الإنجليزية — fallback لو الجداول فاضية أو العمود name_en ما تعبّى
    private const DEFAULT_FIELDS_EN = [
        10 => 'Unspecified', 20 => 'Roads', 30 => 'Buildings',
        40 => 'Electromechanical', 50 => 'Water & Sewage', 60 => 'Public Works',
    ];

    private const DEFAULT_SPECIALIZATIONS_EN = [
        10 => 'Unspecified', 20 => 'Roads', 30 => 'Asphalt Mixes', 40 => 'Bridges & Culverts Concrete',
        50 => 'Earthworks', 60 => 'Buildings', 70 => 'Precast Concrete', 80 => 'Steel Structures',
        90 => 'Prefabricated Buildings', 100 => 'Building Maintenance', 110 => 'Electromechanical',
        120 => 'Electromechanical Maintenance', 130 => 'Mechanical Works', 140 => 'Electrical Works',
        150 => 'Electronics', 160 => 'Water & Sewage', 170 => 'Treatment Plants', 180 => 'Irrigation & Drainage',
        190 => 'Excavation & Mining', 200 => 'Public Works', 210 => 'Railways', 220 => 'Well Drilling',
    ];

    private const DEFAULT_GRADES_EN = [
        'اولى أ' => 'First A', 'اولى ب' => 'First B', 'اولى' => 'First',
        'ثانية' => 'Second', 'ثالثة' => 'Third', 'رابعة' => 'Fourth', 'خامسة' => 'Fifth',
    ];

    // ─── القراءة (مصدر ديناميكي + fallback) ────────────────────────────────

    /** [code => name] للمجالات الفعّالة فقط، مرتّبة. */
    public static function fields(): array
    {
        return Cache::remember('contractor_lookups.fields', self::CACHE_TTL, function () {
            $rows = ContractorField::where('is_active', true)
                ->orderBy('sort_order')->orderBy('id')
                ->pluck('name', 'code')
                ->all();

            return $rows ?: self::DEFAULT_FIELDS;
        });
    }

    /** [code => name] للاختصاصات الفعّالة فقط، مرتّبة. */
    public static function specializations(): array
    {
        return Cache::remember('contractor_lookups.specializations', self::CACHE_TTL, function () {
            $rows = ContractorSpecialization::where('is_active', true)
                ->orderBy('sort_order')->orderBy('id')
                ->pluck('name', 'code')
                ->all();

            return $rows ?: self::DEFAULT_SPECIALIZATIONS;
        });
    }

    /** [field_code => [specialization_code, ...]] */
    public static function fieldSpecializationsMap(): array
    {
        return Cache::remember('contractor_lookups.field_specializations', self::CACHE_TTL, function () {
            $rows = ContractorSpecialization::where('is_active', true)
                ->whereNotNull('contractor_field_id')
                ->with('field:id,code')
                ->orderBy('sort_order')
                ->get();

            if ($rows->isEmpty()) {
                return self::DEFAULT_FIELD_SPECIALIZATIONS;
            }

            return $rows->groupBy(fn ($spec) => $spec->field->code)
                ->map(fn ($group) => $group->pluck('code')->values()->all())
                ->all();
        });
    }

    /** قائمة الدرجات ككائنات كاملة: value/label/level/eligible_fields. */
    public static function gradesList(): array
    {
        return Cache::remember('contractor_lookups.grades', self::CACHE_TTL, function () {
            $rows = ContractorGrade::where('is_active', true)
                ->orderBy('sort_order')->orderBy('id')
                ->get();

            if ($rows->isEmpty()) {
                return collect(self::DEFAULT_SPECIALTY_GRADE_LABELS)
                    ->map(fn ($label, $value) => [
                        'value'           => $value,
                        'label'           => $label,
                        'level'           => self::DEFAULT_GRADE_LEVELS[$value] ?? null,
                        'eligible_fields' => $value === 'اولى أ' ? self::DEFAULT_TOP_TIER_FIELDS : null,
                    ])->values()->all();
            }

            return $rows->map(fn ($g) => [
                'value'           => $g->code,
                'label'           => $g->label,
                'level'           => $g->level,
                'eligible_fields' => $g->eligible_field_codes,
            ])->values()->all();
        });
    }

    /** [grade_code => level] */
    public static function gradeLevels(): array
    {
        return collect(self::gradesList())->pluck('level', 'value')->all();
    }

    /** [grade_code => label] */
    public static function specialtyGradeLabels(): array
    {
        return collect(self::gradesList())->pluck('label', 'value')->all();
    }

    /** المجالات المؤهَّلة للدرجة الخاصة "اولى أ" تحديداً (المادة 37). */
    public static function topTierFields(): array
    {
        $grade = collect(self::gradesList())->firstWhere('value', 'اولى أ');

        if (! $grade) {
            return self::DEFAULT_TOP_TIER_FIELDS;
        }

        // eligible_fields = [] يعني أن الأدمن أزال التقييد عمداً من لوحة الإدارة — لا نرجع
        // للقيمة الافتراضية الثابتة إلا لو كانت null فعلاً (درجة لم تُضبَط أصلاً).
        return $grade['eligible_fields'] ?? self::DEFAULT_TOP_TIER_FIELDS;
    }

    /**
     * الأسماء الإنجليزية [code => name_en] للمجالات/الاختصاصات و[grade_code => label_en] للدرجات،
     * لشهادة العضوية الإنجليزية. تشمل غير الفعّال كمان: شهادة قديمة ممكن تحمل اختصاص انوقف.
     * الكود اللي ما إله ترجمة بالجدول بياخد الترجمة الافتراضية، وإلا بيضل ناقص (null).
     */
    public static function englishNames(): array
    {
        return Cache::remember('contractor_lookups.english', self::CACHE_TTL, function () {
            $fields = ContractorField::whereNotNull('name_en')->where('name_en', '!=', '')->pluck('name_en', 'code')->all();
            $specs  = ContractorSpecialization::whereNotNull('name_en')->where('name_en', '!=', '')->pluck('name_en', 'code')->all();
            $grades = ContractorGrade::whereNotNull('label_en')->where('label_en', '!=', '')->pluck('label_en', 'code')->all();

            return [
                'fields'          => $fields + self::DEFAULT_FIELDS_EN,
                'specializations' => $specs + self::DEFAULT_SPECIALIZATIONS_EN,
                'grades'          => $grades + self::DEFAULT_GRADES_EN,
            ];
        });
    }

    /**
     * صفوف جدول الشهادة الإنجليزية: [field_en, spec_en, grade_en] + قائمة اللي ما إلهم ترجمة
     * (بينطبعوا بالعربي، واللوحة بتنبّه الأدمن عليهم).
     *
     * @return array{rows: list<array{0: string, 1: string, 2: string}>, missing: list<string>}
     */
    public static function englishCertificateRows(?array $specialties): array
    {
        $en = self::englishNames();
        $rows = $missing = [];

        foreach (self::buildFieldsTree($specialties) as $field) {
            $fieldEn = $en['fields'][$field['field_id']] ?? null;
            if (! $fieldEn) {
                $missing[] = $field['field_name'];
            }
            foreach ($field['specializations'] as $spec) {
                $specEn  = $en['specializations'][$spec['spec_id']] ?? null;
                $gradeEn = $spec['grade'] ? ($en['grades'][$spec['grade']] ?? null) : '—';
                if (! $specEn) {
                    $missing[] = $spec['spec_name'];
                }
                if (! $gradeEn) {
                    $missing[] = $spec['grade'];
                }
                $rows[] = [$fieldEn ?: $field['field_name'], $specEn ?: $spec['spec_name'], $gradeEn ?: (string) $spec['grade']];
            }
        }

        return ['rows' => $rows, 'missing' => array_values(array_unique($missing))];
    }

    public static function clearCache(): void
    {
        Cache::forget('contractor_lookups.english');
        Cache::forget('contractor_lookups.fields');
        Cache::forget('contractor_lookups.specializations');
        Cache::forget('contractor_lookups.field_specializations');
        Cache::forget('contractor_lookups.grades');
    }

    // ─── مساعدات ─────────────────────────────────────────────────────────────

    public static function fieldName(?int $type): string
    {
        return self::fields()[$type] ?? 'غير محدد';
    }

    public static function specializationName(?int $type): string
    {
        return self::specializations()[$type] ?? 'غير محدد';
    }

    /** الاختصاصات التابعة لمجال معيّن — مجال "غير محدد"(10) أو غير معروف يعيد كل الاختصاصات. */
    public static function specializationsForField(?int $fieldType): array
    {
        return self::fieldSpecializationsMap()[$fieldType] ?? array_keys(self::specializations());
    }

    /**
     * بناء شجرة المجالات للملف التعريفي:
     * كل مجال يضم اختصاصاته، وكل اختصاص درجته (الحرف + المسمى + المستوى).
     */
    public static function buildFieldsTree(?array $specialties): array
    {
        $fields = [];
        $gradeLabels = self::specialtyGradeLabels();
        $gradeLevels = self::gradeLevels();

        foreach ($specialties ?? [] as $spec) {
            $fieldId = $spec['field_lk_type'] ?? null;
            $specId  = $spec['specialization_lk_type'] ?? null;
            $grade   = $spec['classification'] ?? null;
            $key     = $fieldId ?? 0;

            if (! isset($fields[$key])) {
                $fields[$key] = [
                    'field_id'        => $fieldId,
                    'field_name'      => self::fieldName($fieldId),
                    'specializations' => [],
                ];
            }

            $fields[$key]['specializations'][] = [
                'spec_id'     => $specId,
                'spec_name'   => self::specializationName($specId),
                'grade'       => $grade,
                'grade_label' => $grade ? ($gradeLabels[$grade] ?? $grade) : null,
                'grade_level' => $grade ? ($gradeLevels[$grade] ?? null) : null,
            ];
        }

        return array_values($fields);
    }
}
