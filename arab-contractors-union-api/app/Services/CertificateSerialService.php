<?php

namespace App\Services;

/**
 * مولّد الرقم التسلسلي لشهادة العضوية (يُطبع أسفل الشهادة): XXXXXXXXXXXX (12 خانة، حروف وأرقام فقط بدون شرطات)
 *
 * منفصل عن الرقم المرجعي الداخلي (MC-000123 أعلى الشهادة، مبني على رقم الطلب).
 * - 11 خانة عشوائية (CSPRNG) من أبجدية Crockford بدون الحروف الملتبسة = 55 بت، غير متسلسلة.
 * - الخانة الـ12 خانة فحص (CRC) تكشف أخطاء الكتابة؛ ليست مصدر الأمان: قاعدة البيانات هي المرجع الوحيد،
 *   والرقم غير المسجّل فيها "غير موجودة" مهما كان شكله، والاستعلام محدود بـ throttle.
 * - التفرّد مضمون بقيد unique بقاعدة البيانات؛ المحاولة بتتكرر عند الاصطدام (CertificateRequest::ensureSerialNumber).
 * - بدون شرطات أو مسافات عن قصد: الفواصل المحايدة هي سبب تلخبط الترتيب والشرطات عند النسخ من نص عربي (RTL)،
 *   والرقم المجرّد بيُنسخ ويُلصق سليماً. صفحة التحقق بتتجاهل أي شرطات/مسافات بالمدخل.
 * - صيغتان قديمتان ما زالتا قابلتين للتحقق: 17 خانة (تجريبية على staging)
 *   والمرجعي القديم MC-000123 للشهادات الصادرة قبل الميزة.
 */
class CertificateSerialService
{
    /** كل الحروف/الأرقام المقبولة بالإدخال (تشمل أرقام الصيغ التجريبية السابقة) */
    public const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    /**
     * أبجدية التوليد: حروف لاتينية فقط (22 حرفاً بدون I L O U). الأرقام بتقسّم النص لقطع منفصلة داخل
     * سطر عربي، فبعض قارئات PDF بتعكس ترتيب القطع عند النسخ؛ الحروف وحدها = كلمة واحدة ما بتنعكس.
     */
    private const GEN_ALPHABET = 'ABCDEFGHJKMNPQRSTVWXYZ';

    private const LEN = 12;
    private const LEN_V1 = 17;

    public static function generate(): string
    {
        $body = '';
        for ($i = 0; $i < self::LEN - 1; $i++) {
            $body .= self::GEN_ALPHABET[random_int(0, 21)];
        }

        return self::format($body . self::GEN_ALPHABET[crc32($body) % 22]);
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

        // قارئ PDF عكس ترتيب القطع (حروف/أرقام متتالية) عند النسخ: STS125NEBK8W ← W8NEBK125STS
        if (preg_match_all('/\d+|[A-Z]+/', $raw, $m) && count($m[0]) > 1) {
            $variants[] = implode('', array_reverse($m[0]));
        }
        if (strlen($raw) === self::LEN_V1) {
            // 17 خانة: خانة الفحص آخراً، فبالنسخ العربي بتطلع أولاً
            $variants[] = substr($raw, 13, 4) . substr($raw, 9, 4) . substr($raw, 5, 4) . substr($raw, 1, 4) . $raw[0];
        }

        // نسخ مخربط على مستوى القطع (بين الشرطات): نجرّب كل ترتيب للقطع، كل قطعة كما هي أو معكوسة
        $chunks = array_values(array_filter(preg_split('/[^0-9A-Za-z]+/', strtoupper((string) $input))));
        if (count($chunks) >= 2 && count($chunks) <= 5) {
            foreach (self::permutations($chunks) as $perm) {
                for ($mask = 0; $mask < (1 << count($perm)); $mask++) {
                    $c = '';
                    foreach ($perm as $i => $chunk) {
                        $c .= ($mask >> $i) & 1 ? strrev($chunk) : $chunk;
                    }
                    $c = self::clean($c);
                    if ($c !== null) {
                        $variants[] = $c;
                    }
                }
            }
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

    /** @param string[] $items @return string[][] */
    private static function permutations(array $items): array
    {
        if (count($items) <= 1) {
            return [$items];
        }

        $result = [];
        foreach ($items as $i => $item) {
            $rest = $items;
            unset($rest[$i]);
            foreach (self::permutations(array_values($rest)) as $p) {
                array_unshift($p, $item);
                $result[] = $p;
            }
        }

        return $result;
    }

    /** خانة الفحص سليمة؟ (الرقم القديم MC-000123 بلا فحص → true) */
    public static function hasValidChecksum(string $normalized): bool
    {
        if (preg_match('/^MC-\d{6}$/', $normalized)) {
            return true;
        }

        $compact = str_replace('-', '', $normalized);
        $n = strlen($compact);

        if (! in_array($n, [self::LEN, self::LEN_V1], true)) {
            return false;
        }

        $body = substr($compact, 0, $n - 1);

        // الحالي: خانة فحص من أبجدية الحروف؛ السابق (تجريبي): من الأبجدية الكاملة
        return $compact[$n - 1] === self::GEN_ALPHABET[crc32($body) % 22]
            || $compact[$n - 1] === self::checksum($body);
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

    /** الصيغة القياسية = الخانات المجرّدة (بدون شرطات) */
    private static function format(string $compact): string
    {
        return $compact;
    }
}
