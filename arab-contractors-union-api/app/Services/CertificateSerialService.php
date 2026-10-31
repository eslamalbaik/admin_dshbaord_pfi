<?php

namespace App\Services;

/**
 * مولّد الرقم التسلسلي لشهادة العضوية (يُطبع أسفل الشهادة): XXXX-XXXX-XXXX (12 خانة)
 *
 * منفصل عن الرقم المرجعي الداخلي (MC-000123 أعلى الشهادة، مبني على رقم الطلب).
 * - 11 خانة عشوائية (CSPRNG) من أبجدية Crockford بدون الحروف الملتبسة = 55 بت، غير متسلسلة.
 * - الخانة الـ12 خانة فحص (CRC) تكشف أخطاء الكتابة؛ ليست مصدر الأمان: قاعدة البيانات هي المرجع الوحيد،
 *   والرقم غير المسجّل فيها "غير موجودة" مهما كان شكله، والاستعلام محدود بـ throttle.
 * - التفرّد مضمون بقيد unique بقاعدة البيانات؛ المحاولة بتتكرر عند الاصطدام (CertificateRequest::ensureSerialNumber).
 * - صيغتان قديمتان ما زالتا قابلتين للتحقق: 17 خانة (XXXX-XXXX-XXXX-XXXX-X، تجريبية على staging)
 *   والمرجعي القديم MC-000123 للشهادات الصادرة قبل الميزة.
 */
class CertificateSerialService
{
    public const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private const LEN = 12;
    private const LEN_V1 = 17;

    public static function generate(): string
    {
        $body = '';
        for ($i = 0; $i < self::LEN - 1; $i++) {
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

        $compact = self::clean($raw);

        return $compact === null ? null : self::format($compact);
    }

    /**
     * كل الصيغ المحتملة لرقم تم نسخه من PDF أو شاشة بترتيب عربي (RTL) مقلوب: الرقم كما هو، معكوس
     * الخانات، أو مجموعاته بترتيب معكوس. بنرجّع بس اللي خانة الفحص فيه سليمة، والقاعدة تحسم.
     *
     * @return string[]
     */
    public static function candidates(?string $input): array
    {
        $direct = self::normalize($input);
        if ($direct !== null && str_starts_with($direct, 'MC-')) {
            return [$direct];
        }

        $raw = self::clean(strtoupper((string) $input));
        if ($raw === null) {
            return [];
        }

        $variants = [$raw, strrev($raw), implode('', array_reverse(str_split($raw, 4)))];
        if (strlen($raw) === self::LEN_V1) {
            // 17 خانة: خانة الفحص آخراً، فبالنسخ العربي بتطلع أولاً
            $variants[] = substr($raw, 13, 4) . substr($raw, 9, 4) . substr($raw, 5, 4) . substr($raw, 1, 4) . $raw[0];
        }

        $out = [];
        foreach ($variants as $c) {
            $f = self::format($c);
            if (self::hasValidChecksum($f)) {
                $out[$f] = true;
            }
        }

        return array_keys($out);
    }

    /** خانة الفحص سليمة؟ (الرقم القديم MC-000123 بلا فحص → true) */
    public static function hasValidChecksum(string $normalized): bool
    {
        if (preg_match('/^MC-\d{6}$/', $normalized)) {
            return true;
        }

        $compact = str_replace('-', '', $normalized);
        $n = strlen($compact);

        return in_array($n, [self::LEN, self::LEN_V1], true)
            && self::checksum(substr($compact, 0, $n - 1)) === $compact[$n - 1];
    }

    /** يشيل الفواصل ويصحّح الالتباس (O→0، I/L→1) ويتأكد من الطول والأبجدية؛ null لو غير صالح. */
    private static function clean(string $raw): ?string
    {
        $compact = strtr(preg_replace('/[^0-9A-Z]/', '', $raw), ['O' => '0', 'I' => '1', 'L' => '1']);

        if (! in_array(strlen($compact), [self::LEN, self::LEN_V1], true)
            || ! preg_match('/^[' . self::ALPHABET . ']+$/', $compact)) {
            return null;
        }

        return $compact;
    }

    private static function checksum(string $body): string
    {
        return self::ALPHABET[crc32($body) % 32];
    }

    private static function format(string $compact): string
    {
        if (strlen($compact) === self::LEN_V1) {
            return implode('-', str_split(substr($compact, 0, 16), 4)) . '-' . substr($compact, 16, 1);
        }

        return implode('-', str_split($compact, 4));
    }
}
