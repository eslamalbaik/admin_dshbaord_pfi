<?php

namespace App\Services;

/**
 * مولّد الرقم التسلسلي لشهادة العضوية: MC-XXXXX-XXXXX-CC
 *
 * - 10 خانات عشوائية (CSPRNG) من أبجدية Crockford بدون الحروف الملتبسة (I L O U) = 50 بت،
 *   فتخمين رقم صالح عشوائياً غير عملي، والأرقام غير متسلسلة فما بتنكشف أعداد الشهادات.
 * - خانتا فحص (CRC) آخر الرقم بتكشف أخطاء الإدخال، لكنها ليست مصدر الأمان: قاعدة البيانات
 *   هي المرجع الوحيد، والرقم اللي مش مسجّل فيها "غير موجودة" مهما كان شكله.
 * - التفرّد مضمون بقيد unique بقاعدة البيانات؛ المحاولة بتتكرر عند الاصطدام (انظر CertificateRequest::ensureSerialNumber).
 */
class CertificateSerialService
{
    public const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public static function generate(): string
    {
        $body = '';
        for ($i = 0; $i < 10; $i++) {
            $body .= self::ALPHABET[random_int(0, 31)];
        }

        return self::format($body . self::checksum($body));
    }

    /** يحوّل المدخل (حروف صغيرة/مسافات/شرطات ناقصة) للصيغة القياسية، أو null لو الشكل غير معروف. */
    public static function normalize(?string $input): ?string
    {
        $raw = strtoupper(preg_replace('/[\s\x{200E}\x{200F}\x{202A}-\x{202E}]+/u', '', (string) $input));

        // الرقم القديم المطبوع قبل هالميزة: MC-000123
        if (preg_match('/^MC-?(\d{6})$/', $raw, $m)) {
            return 'MC-' . $m[1];
        }

        $compact = str_replace('-', '', preg_replace('/^MC-?/', '', $raw));
        // O→0 و I,L→1 (التباس شائع بالكتابة اليدوية)
        $compact = strtr($compact, ['O' => '0', 'I' => '1', 'L' => '1']);

        if (! preg_match('/^[' . self::ALPHABET . ']{12}$/', $compact)) {
            return null;
        }

        return self::format($compact);
    }

    /** الرقم بصيغته الجديدة وخانتا الفحص سليمتان؟ (الرقم القديم بلا فحص → true) */
    public static function hasValidChecksum(string $normalized): bool
    {
        if (preg_match('/^MC-\d{6}$/', $normalized)) {
            return true;
        }

        $compact = str_replace('-', '', substr($normalized, 3)); // بدون بادئة MC-

        return strlen($compact) === 12
            && self::checksum(substr($compact, 0, 10)) === substr($compact, 10, 2);
    }

    private static function checksum(string $body): string
    {
        $n = crc32($body) % 1024;

        return self::ALPHABET[intdiv($n, 32)] . self::ALPHABET[$n % 32];
    }

    private static function format(string $compact): string
    {
        return 'MC-' . substr($compact, 0, 5) . '-' . substr($compact, 5, 5) . '-' . substr($compact, 10, 2);
    }
}
