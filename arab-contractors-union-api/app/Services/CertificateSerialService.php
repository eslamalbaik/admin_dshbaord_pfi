<?php

namespace App\Services;

/**
 * مولّد الرقم التسلسلي لشهادة العضوية (يُطبع أسفل الشهادة): XXXX-XXXX-XXXX-XXXX-C
 *
 * منفصل عن الرقم المرجعي الداخلي (MC-000123 أعلى الشهادة، مبني على رقم الطلب).
 * - 16 خانة عشوائية (CSPRNG) من أبجدية Crockford بدون الحروف الملتبسة = 80 بت، غير متسلسلة.
 * - خانة فحص واحدة (CRC) تكشف أخطاء الكتابة؛ ليست مصدر الأمان: قاعدة البيانات هي المرجع الوحيد،
 *   والرقم غير المسجّل فيها "غير موجودة" مهما كان شكله، والاستعلام محدود بـ throttle.
 * - التفرّد مضمون بقيد unique بقاعدة البيانات؛ المحاولة بتتكرر عند الاصطدام (CertificateRequest::ensureSerialNumber).
 * - الشهادات القديمة المطبوع عليها MC-000123 فقط بتضل قابلة للتحقق بهالرقم (legacy).
 */
class CertificateSerialService
{
    public const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public static function generate(): string
    {
        $body = '';
        for ($i = 0; $i < 16; $i++) {
            $body .= self::ALPHABET[random_int(0, 31)];
        }

        return self::format($body . self::checksum($body));
    }

    /** يحوّل المدخل (حروف صغيرة/مسافات/شرطات ناقصة) للصيغة القياسية، أو null لو الشكل غير معروف. */
    public static function normalize(?string $input): ?string
    {
        $raw = strtoupper(preg_replace('/[\s\x{200E}\x{200F}\x{202A}-\x{202E}]+/u', '', (string) $input));

        // الرقم المرجعي القديم المطبوع قبل هالميزة: MC-000123
        if (preg_match('/^MC-?(\d{6})$/', $raw, $m)) {
            return 'MC-' . $m[1];
        }

        // O→0 و I,L→1 (التباس شائع بالكتابة اليدوية)
        $compact = strtr(str_replace('-', '', $raw), ['O' => '0', 'I' => '1', 'L' => '1']);

        if (! preg_match('/^[' . self::ALPHABET . ']{17}$/', $compact)) {
            return null;
        }

        return self::format($compact);
    }

    /** خانة الفحص سليمة؟ (الرقم القديم بلا فحص → true) */
    public static function hasValidChecksum(string $normalized): bool
    {
        if (preg_match('/^MC-\d{6}$/', $normalized)) {
            return true;
        }

        $compact = str_replace('-', '', $normalized);

        return strlen($compact) === 17 && self::checksum(substr($compact, 0, 16)) === $compact[16];
    }

    private static function checksum(string $body): string
    {
        return self::ALPHABET[crc32($body) % 32];
    }

    private static function format(string $compact): string
    {
        return implode('-', str_split(substr($compact, 0, 16), 4)) . '-' . substr($compact, 16, 1);
    }
}
