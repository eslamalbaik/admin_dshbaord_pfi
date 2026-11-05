<?php

namespace App\Support;

/**
 * الدول المدعومة لرقم جوال المقاول (التسجيل واستعادة كلمة المرور).
 *
 * contractors.phone يخزّن الرقم الوطني فقط (بدون صفر البداية وبدون رمز الدولة،
 * مثل 597245424) و contractors.phone_country_code يخزّن رمز الدولة (970 افتراضياً).
 * كل الأرقام القديمة فلسطينية، فالـ migration يعطيها 970.
 */
class PhoneCountries
{
    public const DEFAULT_CODE = '970';

    /**
     * 970 و 972 يخدمان نفس الأرقام الفلسطينية (تشارك الشبكة) — رقم مخزّن بأحدهما
     * يُعثر عليه عند إدخاله بالآخر.
     */
    private const ALIASES = [
        '970' => ['970', '972'],
        '972' => ['972', '970'],
    ];

    /** طول الرقم الوطني (بدون الصفر) لكل دولة: [min, max] */
    private const COUNTRIES = [
        ['iso' => 'PS', 'code' => '970', 'name_ar' => 'فلسطين',   'name_en' => 'Palestine',    'flag' => '🇵🇸', 'length' => [9, 9],   'example' => '599123456'],
        ['iso' => 'IL', 'code' => '972', 'name_ar' => 'الداخل المحتل', 'name_en' => 'Israel',  'flag' => '🇮🇱', 'length' => [9, 9],   'example' => '541234567'],
        ['iso' => 'EG', 'code' => '20',  'name_ar' => 'مصر',      'name_en' => 'Egypt',        'flag' => '🇪🇬', 'length' => [10, 10], 'example' => '1001234567'],
        ['iso' => 'JO', 'code' => '962', 'name_ar' => 'الأردن',   'name_en' => 'Jordan',       'flag' => '🇯🇴', 'length' => [9, 9],   'example' => '791234567'],
        ['iso' => 'SA', 'code' => '966', 'name_ar' => 'السعودية', 'name_en' => 'Saudi Arabia', 'flag' => '🇸🇦', 'length' => [9, 9],   'example' => '501234567'],
        ['iso' => 'AE', 'code' => '971', 'name_ar' => 'الإمارات', 'name_en' => 'UAE',          'flag' => '🇦🇪', 'length' => [9, 9],   'example' => '501234567'],
        ['iso' => 'QA', 'code' => '974', 'name_ar' => 'قطر',      'name_en' => 'Qatar',        'flag' => '🇶🇦', 'length' => [8, 8],   'example' => '33123456'],
        ['iso' => 'KW', 'code' => '965', 'name_ar' => 'الكويت',   'name_en' => 'Kuwait',       'flag' => '🇰🇼', 'length' => [8, 8],   'example' => '50012345'],
        ['iso' => 'BH', 'code' => '973', 'name_ar' => 'البحرين',  'name_en' => 'Bahrain',      'flag' => '🇧🇭', 'length' => [8, 8],   'example' => '36001234'],
        ['iso' => 'OM', 'code' => '968', 'name_ar' => 'عُمان',    'name_en' => 'Oman',         'flag' => '🇴🇲', 'length' => [8, 8],   'example' => '92123456'],
        ['iso' => 'LB', 'code' => '961', 'name_ar' => 'لبنان',    'name_en' => 'Lebanon',      'flag' => '🇱🇧', 'length' => [7, 8],   'example' => '71123456'],
        ['iso' => 'SY', 'code' => '963', 'name_ar' => 'سوريا',    'name_en' => 'Syria',        'flag' => '🇸🇾', 'length' => [9, 9],   'example' => '944567890'],
        ['iso' => 'IQ', 'code' => '964', 'name_ar' => 'العراق',   'name_en' => 'Iraq',         'flag' => '🇮🇶', 'length' => [10, 10], 'example' => '7912345678'],
        ['iso' => 'TR', 'code' => '90',  'name_ar' => 'تركيا',    'name_en' => 'Turkey',       'flag' => '🇹🇷', 'length' => [10, 10], 'example' => '5012345678'],
    ];

    /** القائمة كما تُعرض لتطبيق الموبايل */
    public static function all(): array
    {
        return array_map(fn (array $c) => [
            'iso'        => $c['iso'],
            'code'       => $c['code'],
            'dial_code'  => '+' . $c['code'],
            'name_ar'    => $c['name_ar'],
            'name_en'    => $c['name_en'],
            'flag'       => $c['flag'],
            'min_length' => $c['length'][0],
            'max_length' => $c['length'][1],
            'example'    => $c['example'],
            'is_default' => $c['code'] === self::DEFAULT_CODE,
        ], self::COUNTRIES);
    }

    public static function codes(): array
    {
        return array_column(self::COUNTRIES, 'code');
    }

    /** رموز الدولة التي يُبحث بها عن رقم مُدخل برمز $code */
    public static function lookupCodes(string $code): array
    {
        return self::ALIASES[$code] ?? [$code];
    }

    /**
     * يحوّل ما أدخله المستخدم إلى [code, national] أو null إن كان غير صالح.
     *
     * يقبل: "0599123456" / "599123456" مع country_code، أو رقماً دولياً كاملاً
     * ("+970599123456" / "00970599123456") فيُستخرج رمز الدولة منه ويُتجاهل country_code.
     * الأرقام العربية-الهندية (٠-٩) مقبولة.
     *
     * @return array{code: string, national: string}|null
     */
    public static function normalize(?string $countryCode, ?string $phone): ?array
    {
        $phone = trim(strtr((string) $phone, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]));
        $isInternational = str_starts_with($phone, '+') || str_starts_with($phone, '00');
        $digits = preg_replace('/\D/', '', $phone);

        if ($isInternational) {
            $digits = preg_replace('/^00/', '', $digits);
            $country = self::matchPrefix($digits);
            if (! $country) {
                return null;
            }
            $code = $country['code'];
            $national = substr($digits, strlen($code));
        } else {
            $code = preg_replace('/\D/', '', (string) $countryCode) ?: self::DEFAULT_CODE;
            $code = preg_replace('/^00/', '', $code);
            $country = self::find($code);
            if (! $country) {
                return null;
            }
            $national = $digits;
            // رمز الدولة مكتوب بدون + (مثل 970599123456)
            if (str_starts_with($national, $code) && strlen($national) > $country['length'][1] + 1) {
                $national = substr($national, strlen($code));
            }
        }

        $national = ltrim($national, '0');
        [$min, $max] = $country['length'];

        if (strlen($national) < $min || strlen($national) > $max) {
            return null;
        }

        return ['code' => $code, 'national' => $national];
    }

    public static function international(?string $code, ?string $national): string
    {
        return '+' . ($code ?: self::DEFAULT_CODE) . ltrim((string) $national, '0');
    }

    private static function find(string $code): ?array
    {
        foreach (self::COUNTRIES as $c) {
            if ($c['code'] === $code) {
                return $c;
            }
        }

        return null;
    }

    private static function matchPrefix(string $digits): ?array
    {
        // الأطول أولاً حتى لا يلتقط "20" رقماً يبدأ بـ "20x" لدولة أخرى
        $countries = self::COUNTRIES;
        usort($countries, fn ($a, $b) => strlen($b['code']) <=> strlen($a['code']));

        foreach ($countries as $c) {
            if (str_starts_with($digits, $c['code'])) {
                return $c;
            }
        }

        return null;
    }
}
