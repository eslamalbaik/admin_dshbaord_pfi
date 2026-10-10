<?php

namespace App\Support;

/**
 * سجل المحددات الهامة — تعريف الأحداث المخالفة للوضع الطبيعي اللي بتظهر
 * بقسم «المحددات الهامة» بصفحة سجل النشاط.
 *
 * لإضافة نوع حدث جديد:
 *   1. أضف مدخلاً في EVENTS (المسمّى العربي + مسمّيات الحقول اللي بتتغيّر).
 *   2. استدعِ AuditLogService::recordCritical() من مكان التعديل مع القيم قبل/بعد.
 * الواجهة بتعرض أي حدث جديد تلقائياً بدون تعديل.
 */
class CriticalEvents
{
    public const CATEGORY_MEMBERSHIP_FEE = 'membership_fee';
    public const CATEGORY_CERTIFICATE    = 'membership_certificate';
    public const CATEGORY_SUPERVISORS    = 'supervisors';
    public const CATEGORY_BALANCES       = 'balances';

    public const CATEGORIES = [
        self::CATEGORY_MEMBERSHIP_FEE => 'رسوم العضوية',
        self::CATEGORY_CERTIFICATE    => 'إصدار شهادة العضوية',
        self::CATEGORY_SUPERVISORS    => 'المشرفون والصلاحيات',
        self::CATEGORY_BALANCES       => 'أرصدة المقاولين',
    ];

    public const EVENTS = [
        // ── رسوم العضوية ──
        'grade_fee.updated' => [
            'label'    => 'تعديل رسوم درجة تصنيف',
            'category' => self::CATEGORY_MEMBERSHIP_FEE,
            'fields'   => [
                'grade_label'          => 'مسمّى الدرجة',
                'registration_fee_jod' => 'رسوم التسجيل (د.أ)',
                'annual_fee_jod'       => 'الاشتراك السنوي (د.أ)',
            ],
        ],
        'due.discount_applied' => [
            'label'    => 'خصم على رسوم مقاول',
            'category' => self::CATEGORY_MEMBERSHIP_FEE,
            'fields'   => [
                'amount_jod'          => 'المبلغ المستحق (د.أ)',
                'original_amount_jod' => 'المبلغ قبل الخصم (د.أ)',
                'discount_type'       => 'نوع الخصم',
                'discount_value'      => 'قيمة الخصم',
                'discount_amount_jod' => 'إجمالي الخصم (د.أ)',
            ],
        ],
        'due.discount_applied_bulk' => [
            'label'    => 'خصم جماعي على رسوم المقاولين',
            'category' => self::CATEGORY_MEMBERSHIP_FEE,
            'fields'   => [
                'discount_type'  => 'نوع الخصم',
                'discount_value' => 'قيمة الخصم',
            ],
        ],

        // ── أرصدة المقاولين ──
        'balance.adjusted' => [
            'label'    => 'تعديل رصيد مقاول يدوياً',
            'category' => self::CATEGORY_BALANCES,
            'fields'   => [
                'net_jod' => 'الرصيد الصافي (د.أ)',
                'status'  => 'حالة العضوية',
            ],
        ],

        // ── إصدار شهادة العضوية ──
        'certificate.regenerated' => [
            'label'    => 'إعادة إصدار شهادة عضوية',
            'category' => self::CATEGORY_CERTIFICATE,
            'fields'   => self::CERTIFICATE_FIELDS,
        ],
        'certificate.admin_issued_membership' => [
            'label'    => 'إصدار شهادة عضوية ببيانات مخالفة لسجل المقاول',
            'category' => self::CATEGORY_CERTIFICATE,
            'fields'   => self::CERTIFICATE_FIELDS,
        ],
        'certificate.issued' => [
            'label'    => 'استبدال ملف شهادة صادرة',
            'category' => self::CATEGORY_CERTIFICATE,
            'fields'   => self::CERTIFICATE_FIELDS,
        ],

        // ── المشرفون والصلاحيات ──
        'supervisor.created' => [
            'label'    => 'إنشاء مشرف',
            'category' => self::CATEGORY_SUPERVISORS,
            'fields'   => self::SUPERVISOR_FIELDS,
        ],
        'supervisor.updated' => [
            'label'    => 'تعديل بيانات أو صلاحيات مشرف',
            'category' => self::CATEGORY_SUPERVISORS,
            'fields'   => self::SUPERVISOR_FIELDS,
        ],
        'supervisor.status_changed' => [
            'label'    => 'تفعيل/تعطيل حساب مشرف',
            'category' => self::CATEGORY_SUPERVISORS,
            'fields'   => self::SUPERVISOR_FIELDS,
        ],
        'supervisor.password_reset' => [
            'label'    => 'إعادة تعيين كلمة مرور مشرف',
            'category' => self::CATEGORY_SUPERVISORS,
            'fields'   => self::SUPERVISOR_FIELDS,
        ],
    ];

    /** أحداث حرجة دائماً (بغض النظر عن تفاصيلها) — الباقي حرج بشرط يحدده مكان التسجيل */
    public const ALWAYS_CRITICAL = [
        'grade_fee.updated',
        'due.discount_applied',
        'due.discount_applied_bulk',
        'balance.adjusted',
        'certificate.regenerated',
        'supervisor.created',
        'supervisor.updated',
        'supervisor.status_changed',
        'supervisor.password_reset',
    ];

    private const CERTIFICATE_FIELDS = [
        'address'          => 'العنوان',
        'decision_number'  => 'رقم قرار التصنيف',
        'decision_date'    => 'تاريخ قرار التصنيف',
        'issued_at'        => 'تاريخ الإصدار',
        'certificate_file' => 'ملف الشهادة',
    ];

    private const SUPERVISOR_FIELDS = [
        'name'        => 'الاسم',
        'email'       => 'البريد الإلكتروني',
        'phone'       => 'الهاتف',
        'is_active'   => 'الحالة',
        'permissions' => 'الصلاحيات',
        'password'    => 'كلمة المرور',
    ];

    public static function definition(string $action): ?array
    {
        return self::EVENTS[$action] ?? null;
    }

    public static function label(string $action): ?string
    {
        return self::EVENTS[$action]['label'] ?? null;
    }

    public static function fieldLabel(string $action, string $field): string
    {
        return self::EVENTS[$action]['fields'][$field] ?? $field;
    }

    /**
     * يحوّل before/after المخزّنة بالـ meta لقائمة تغييرات جاهزة للعرض.
     *
     * @return array<int, array{field: string, label: string, before: mixed, after: mixed}>
     */
    public static function changes(string $action, array $meta): array
    {
        $before = $meta['before'] ?? [];
        $after  = $meta['after'] ?? [];

        $fields = array_values(array_unique(array_merge(array_keys($before), array_keys($after))));

        return array_map(fn ($field) => [
            'field'  => $field,
            'label'  => self::fieldLabel($action, $field),
            'before' => $before[$field] ?? null,
            'after'  => $after[$field] ?? null,
        ], $fields);
    }
}
