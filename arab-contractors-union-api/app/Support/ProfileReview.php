<?php

namespace App\Support;

use App\Models\City;
use App\Models\Contractor;
use App\Models\Governorate;
use App\Models\Setting;
use App\Services\ContractorFileService;
use Carbon\Carbon;

/**
 * قواعد مراجعة تعديلات الملف التعريفي من التطبيق — مصدر واحد للفئات والتسميات.
 *
 * الفئات (قرار المالك 2026-11-09):
 *   - REVIEW_FIELDS + كل المستندات: اعتماد مسبق. القيمة الرسمية ما بتتغيّر لحد ما الإدارة تعتمد.
 *   - INSTANT_FIELDS: حفظ فوري + مراجعة لاحقة (الإدارة بتأكّد أو بترجّع).
 *   - LOCKED_FIELDS: مقفلة عن التطبيق أصلاً (تعديلها من لوحة التحكم فقط).
 *   - fcm_token والشعار: خارج المراجعة دائماً (التوكن لو دخل المراجعة بتوقف الإشعارات).
 */
class ProfileReview
{
    public const SETTING_KEY = 'profile_review_enabled';

    public const REVIEW_FIELDS = [
        'capital', 'legal_form', 'registration_date', 'established_date', 'company_purposes', 'license_number',
    ];

    public const INSTANT_FIELDS = [
        'email', 'governorate_id', 'city_id', 'address', 'district', 'building', 'floor', 'fax', 'notes',
    ];

    public const LOCKED_FIELDS = [
        'name', 'membership_number', 'commercial_register', 'owner_name', 'authorized_person',
        'authorized_person_id_number', 'authorized_person_phone', 'authorized_person_whatsapp',
        'partners', 'classification', 'specialties', 'phone',
    ];

    /** الحقول اللي بينسجّل تاريخها (من أي مصدر) بسجل التغييرات. */
    public static function trackedFields(): array
    {
        return array_values(array_unique(array_merge(
            self::REVIEW_FIELDS,
            self::INSTANT_FIELDS,
            self::LOCKED_FIELDS,
            array_keys(ContractorFileService::FILE_FIELDS),
            ['logo', 'status', 'is_frozen'],
        )));
    }

    public const TEXT_LABELS = [
        'name'                        => 'اسم الشركة',
        'membership_number'           => 'رقم العضوية',
        'commercial_register'         => 'رقم السجل التجاري',
        'license_number'              => 'رقم رخصة البلدية',
        'classification'              => 'التصنيف',
        'specialties'                 => 'المجالات والاختصاصات',
        'established_date'            => 'تاريخ التأسيس',
        'owner_name'                  => 'اسم المالك',
        'email'                       => 'البريد الإلكتروني',
        'phone'                       => 'الجوال',
        'governorate_id'              => 'المحافظة',
        'city_id'                     => 'المدينة',
        'district'                    => 'الحي',
        'address'                     => 'العنوان',
        'partners'                    => 'الشركاء',
        'fax'                         => 'الفاكس',
        'building'                    => 'العمارة',
        'floor'                       => 'الطابق',
        'notes'                       => 'ملاحظات',
        'capital'                     => 'رأس المال',
        'registration_date'           => 'تاريخ التسجيل',
        'legal_form'                  => 'الشكل القانوني',
        'company_purposes'            => 'غايات الشركة',
        'authorized_person'           => 'المفوض بالتوقيع',
        'authorized_person_id_number' => 'رقم هوية المفوض',
        'authorized_person_phone'     => 'رقم جوال المفوض',
        'authorized_person_whatsapp'  => 'رقم واتساب المفوض',
        'logo'                        => 'الشعار',
        'status'                      => 'حالة المقاول',
        'is_frozen'                   => 'تجميد الحساب',
    ];

    /** المجموعة اللي بينعرض فيها الحقل بشاشة المراجعة. */
    public static function group(string $field): string
    {
        if (self::isFile($field)) {
            return 'documents';
        }

        return in_array($field, self::INSTANT_FIELDS, true) ? 'contact' : 'legal';
    }

    public const GROUP_LABELS = [
        'legal'     => 'البيانات القانونية',
        'contact'   => 'بيانات التواصل والعنوان',
        'documents' => 'المستندات',
    ];

    public static function enabled(): bool
    {
        $value = Setting::get(self::SETTING_KEY);

        if ($value === null) {
            return (bool) config('profile_review.default_enabled');
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function isFile(string $field): bool
    {
        return isset(ContractorFileService::FILE_FIELDS[$field]);
    }

    /** pre_approval | post_review | null (خارج المراجعة) */
    public static function modeFor(string $field): ?string
    {
        if (self::isFile($field) || in_array($field, self::REVIEW_FIELDS, true)) {
            return 'pre_approval';
        }

        if (in_array($field, self::INSTANT_FIELDS, true)) {
            return 'post_review';
        }

        return null;
    }

    public static function label(string $field): string
    {
        return Contractor::DOCUMENT_LABELS[$field] ?? self::TEXT_LABELS[$field] ?? $field;
    }

    /**
     * تحويل القيمة لنص للتخزين والمقارنة: التواريخ Y-m-d، المصفوفات JSON، الفراغ null.
     */
    public static function normalize(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        if (in_array($field, ['established_date', 'registration_date'], true)) {
            try {
                return Carbon::parse($value)->toDateString();
            } catch (\Throwable) {
                return (string) $value;
            }
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->toDateString();
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return trim((string) $value);
    }

    /** القيمة كما بتنعرض للإنسان (أسماء المحافظة/المدينة بدل الأرقام…). */
    public static function display(string $field, ?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($field) {
            'governorate_id' => Governorate::find($value)?->name ?? $value,
            'city_id'        => City::find($value)?->name ?? $value,
            'is_frozen'      => $value === '1' ? 'مجمّد' : 'غير مجمّد',
            'status'         => match ($value) {
                'active' => 'نشط', 'expired' => 'منتهي', 'suspended' => 'موقوف', 'pending' => 'قيد المراجعة', default => $value,
            },
            default => self::isFile($field) || $field === 'logo' ? basename($value) : $value,
        };
    }

    /** رابط ملف مخزّن على قرص public (للمستندات القديمة والجديدة بالمقارنة). */
    public static function fileUrl(?string $path): ?string
    {
        return $path ? \Illuminate\Support\Facades\Storage::disk('public')->url($path) : null;
    }

    /** عدد أيام العمل (أحد–خميس) منذ تاريخ — لمهلة المراجعة. */
    public static function workingDaysSince(Carbon $from, ?Carbon $now = null): int
    {
        $now ??= now();
        $days = 0;
        $cursor = $from->copy()->startOfDay()->addDay();

        while ($cursor->lte($now)) {
            if (! in_array($cursor->dayOfWeek, [Carbon::FRIDAY, Carbon::SATURDAY], true)) {
                $days++;
            }
            $cursor->addDay();
        }

        return $days;
    }
}
