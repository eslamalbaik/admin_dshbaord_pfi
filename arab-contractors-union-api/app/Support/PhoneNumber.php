<?php

namespace App\Support;

/**
 * توحيد أرقام الجوال الفلسطينية لصيغة محلية واحدة (05XXXXXXXX) — عشان "+970 59-912 3456"
 * و"00970599123456" و"599123456" و"0599 123 456" يطلعوا نفس الرقم عند التخزين والمطابقة.
 */
class PhoneNumber
{
    public static function normalize(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        // مفتاح فلسطين 970 أو 972 ← صفر محلي
        if (strlen($digits) >= 11 && (str_starts_with($digits, '970') || str_starts_with($digits, '972'))) {
            $digits = '0' . substr($digits, 3);
        }
        // رقم جوال بدون الصفر بالبداية (599123456)
        if (strlen($digits) === 9 && str_starts_with($digits, '5')) {
            $digits = '0' . $digits;
        }

        return $digits;
    }

    /** هل المدخل شكله رقم جوال (مش رقم عضوية)؟ */
    public static function looksLikeMobile(?string $value): bool
    {
        return (bool) preg_match('/^05\d{8}$/', (string) self::normalize($value));
    }
}
