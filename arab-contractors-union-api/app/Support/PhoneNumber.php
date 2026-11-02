<?php

namespace App\Support;

use App\Models\CountryDialCode;
use Illuminate\Support\Collection;

/**
 * توحيد أرقام الجوال لصيغة تخزين وحدة، عشان المطابقة (دخول التطبيق، رموز التحقق) تكون مباشرة:
 *
 * - فلسطين (970 أو 972، أو رقم محلي بدون مقدمة) ← صيغة محلية 05XXXXXXXX:
 *   "+970 59-912 3456" و"00970599123456" و"599123456" و"0599 123 456" كلهم 0599123456.
 * - باقي الدول المفعّلة بالإعدادات (الأردن، مصر، السعودية…) ← المقدمة + الرقم المحلي بدون الصفر:
 *   "+962 79 123 4567" و"00962 0791234567" و"962791234567" كلهم 962791234567.
 */
class PhoneNumber
{
    /** مقدمات فلسطين: الرقم المحلي بعدها بينحفظ بصيغة 05XXXXXXXX (زي ما كان قبل دعم باقي الدول). */
    private const PALESTINE_CODES = ['970', '972'];

    public static function normalize(?string $value): ?string
    {
        $raw = trim((string) $value);
        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === '') {
            return null;
        }

        $international = str_starts_with($raw, '+') || str_starts_with($digits, '00');
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        // مفتاح فلسطين 970 أو 972 ← صفر محلي (+970 0599… كمان مقبول)
        if (strlen($digits) >= 11 && in_array(substr($digits, 0, 3), self::PALESTINE_CODES, true)) {
            return '0' . ltrim(substr($digits, 3), '0');
        }
        // رقم جوال فلسطيني بدون الصفر بالبداية (599123456)
        if (! $international && strlen($digits) === 9 && str_starts_with($digits, '5')) {
            return '0' . $digits;
        }

        // دولة تانية: المقدمة + الرقم المحلي بدون صفر محلي (962 0791234567 ← 962791234567)
        if ($international || ! str_starts_with($digits, '0')) {
            $parsed = self::parseInternational($digits);
            if ($parsed) {
                return $parsed['dial_code'] . $parsed['national'];
            }
        }

        return $digits;
    }

    /**
     * يفكّ رقم دولي (أرقام فقط، بدون + أو 00) لمقدمة دولة مفعّلة ورقم محلي مطابق لقاعدتها.
     *
     * @return array{dial_code: string, national: string}|null
     */
    public static function parseInternational(string $digits): ?array
    {
        foreach (self::dialCodes() as $code) {
            if (! str_starts_with($digits, $code->dial_code)) {
                continue;
            }
            $national = substr($digits, strlen($code->dial_code));
            if (! $code->acceptsNational($national) && str_starts_with($national, '0') && $code->acceptsNational(substr($national, 1))) {
                $national = substr($national, 1);
            }
            if ($code->acceptsNational($national)) {
                return ['dial_code' => $code->dial_code, 'national' => $national];
            }
        }

        return null;
    }

    /**
     * هل المدخل شكله رقم جوال (مش رقم عضوية)؟ جوال فلسطيني، أو أي رقم مكتوب بمقدمة دولية
     * (+962… / 00962…)، أو رقم بمقدمة دولة مفعّلة ومطابق لقاعدتها.
     */
    public static function looksLikeMobile(?string $value): bool
    {
        $raw = trim((string) $value);
        $normalized = (string) self::normalize($raw);

        if (preg_match('/^05\d{8}$/', $normalized)) {
            return true;
        }

        $digits = preg_replace('/\D+/', '', $raw);
        if ((str_starts_with($raw, '+') || str_starts_with($digits, '00')) && strlen($normalized) >= 8) {
            return true;
        }

        return strlen($normalized) >= 10 && self::parseInternational($normalized) !== null;
    }

    /** الرقم المحفوظ بصيغة دولية للعرض والاتصال: 0599123456 ← +970599123456، 962791234567 ← +962791234567. */
    public static function international(?string $stored): ?string
    {
        $normalized = self::normalize($stored);
        if (! $normalized) {
            return null;
        }

        if (preg_match('/^0(5\d{8})$/', $normalized, $m)) {
            return '+970' . $m[1];
        }

        return self::parseInternational($normalized) ? '+' . $normalized : $normalized;
    }

    /** المقدمات المفعّلة، الأطول أولاً (عشان 9665… ما تنقرا 966 + 5… غلط لو في مقدمة أطول). */
    private static function dialCodes(): Collection
    {
        try {
            $codes = CountryDialCode::active()->get();
        } catch (\Throwable) {
            // قبل إنشاء جدول المقدمات (هجرات قديمة) — فلسطين بتضل شغّالة من الفوق
            return collect();
        }

        return $codes
            ->reject(fn ($c) => in_array($c->dial_code, self::PALESTINE_CODES, true))
            ->sortByDesc(fn ($c) => strlen($c->dial_code))
            ->values();
    }
}
