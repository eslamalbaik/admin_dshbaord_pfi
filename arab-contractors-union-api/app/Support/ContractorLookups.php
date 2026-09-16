<?php

namespace App\Support;

/**
 * جداول المرجعية للمجالات والاختصاصات والدرجات (مصدر واحد — REQ-08).
 * تُستخدم في Get Profile وتصدير الـ PDF بدل تكرار الخرائط في كل مكان.
 */
class ContractorLookups
{
    /** المجالات الرئيسية — field_lk_type */
    public const FIELDS = [
        10 => 'غير محدد',
        20 => 'طرق',
        30 => 'ابنية',
        40 => 'كهروميكانيك',
        50 => 'الميــاه/المجــارى',
        60 => 'أشغال عامه',
    ];

    /** الاختصاصات التفصيلية — specialization_lk_type */
    public const SPECIALIZATIONS = [
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

    /**
     * المستوى الرقمي لكل درجة تصنيف — رمز موحَّد (canonical) بعد تطبيع البيانات (REQ-FEE-01).
     * الأقل رقماً = الأعلى درجة. "اولى أ" درجة خاصة لا تصحّ إلا لمجالي طرق(20) وابنية(30).
     */
    public const GRADE_LEVELS = [
        'اولى أ' => 1,
        'اولى ب' => 2,
        'ثانية'  => 3,
        'ثالثة'  => 4,
        'رابعة'  => 5,
        'خامسة'  => 6,
    ];

    /** مسمّيات درجات التخصص (خاصة بـ specialties[].classification) — منفصلة عن Contractor::CLASSIFICATION_LABELS */
    public const SPECIALTY_GRADE_LABELS = [
        'اولى أ' => 'الدرجة الأولى (أ)',
        'اولى ب' => 'الدرجة الأولى (ب)',
        'ثانية'  => 'الدرجة الثانية',
        'ثالثة'  => 'الدرجة الثالثة',
        'رابعة'  => 'الدرجة الرابعة',
        'خامسة'  => 'الدرجة الخامسة',
    ];

    /** المجالات التي تصحّ لها درجة "اولى أ" الخاصة (المادة 37) */
    public const TOP_TIER_FIELDS = [20, 30];

    public static function fieldName(?int $type): string
    {
        return self::FIELDS[$type] ?? 'غير محدد';
    }

    public static function specializationName(?int $type): string
    {
        return self::SPECIALIZATIONS[$type] ?? 'غير محدد';
    }

    /**
     * بناء شجرة المجالات للملف الشخصي:
     * كل مجال يضم اختصاصاته، وكل اختصاص درجته (الحرف + المسمى + المستوى).
     */
    public static function buildFieldsTree(?array $specialties): array
    {
        $fields = [];

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
                'grade_label' => $grade ? (self::SPECIALTY_GRADE_LABELS[$grade] ?? $grade) : null,
                'grade_level' => $grade ? (self::GRADE_LEVELS[$grade] ?? null) : null,
            ];
        }

        return array_values($fields);
    }
}
