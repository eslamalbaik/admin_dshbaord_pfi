<?php

namespace App\Support;

/**
 * الحد الفعلي لرفع الملفات (TASK-16 #2).
 *
 * قاعدة التحقق في Laravel لم تكن هي السقف الحقيقي إطلاقاً: PHP يُسقط أي ملف يتجاوز
 * upload_max_filesize *قبل* أن يصل الطلب إلى Laravel، فلا تصدر رسالة حجم بل يغيب
 * الحقل كلياً — وهو سبب ظهور «يرجى التحقق من المدخلات» بلا تفسير.
 *
 * لذلك يُحسب السقف من ini وقت التشغيل لا من رقم ثابت: تبقى الواجهة صادقة سواء
 * رُفعت حدود الخادم أم لا، وتُصحّح نفسها تلقائياً بعد رفعها.
 */
class UploadLimits
{
    /** السقف المطلوب من جانب التطبيق (KB) — 12 ميجابايت. */
    public const CONFIGURED_MAX_KB = 12288;

    /** الامتدادات المسموح بها — نفس قائمة mimes في ContractorController. */
    public const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];

    /** الحد الفعلي لملف واحد (KB): الأصغر بين قاعدة Laravel وما يسمح به PHP. */
    public static function maxFileKb(): int
    {
        return (int) min(self::CONFIGURED_MAX_KB, self::iniBytes('upload_max_filesize') / 1024);
    }

    /** الحد الفعلي لحجم الطلب كاملاً (KB) — يحكم مجموع المستندات المرفوعة دفعة واحدة. */
    public static function maxPostKb(): int
    {
        return (int) (self::iniBytes('post_max_size') / 1024);
    }

    /** أقصى عدد ملفات في الطلب الواحد. */
    public static function maxFiles(): int
    {
        return (int) ini_get('max_file_uploads');
    }

    /** الحمولة التي تستهلكها الواجهة لعرض القيود وللتحقق قبل بدء الرفع. */
    public static function toArray(): array
    {
        return [
            'max_file_kb'        => self::maxFileKb(),
            'max_file_mb'        => round(self::maxFileKb() / 1024, 1),
            'max_post_kb'        => self::maxPostKb(),
            'max_post_mb'        => round(self::maxPostKb() / 1024, 1),
            'max_files'          => self::maxFiles(),
            'allowed_extensions' => self::ALLOWED_EXTENSIONS,
        ];
    }

    /** تحويل صيغ ini المختصرة (2M، 8M، 512K، 1G) إلى بايت. */
    private static function iniBytes(string $key): float
    {
        $value = trim((string) ini_get($key));

        if ($value === '') {
            return INF; // غير محدّد — لا يقيّد شيئاً
        }

        $number = (float) $value;
        $unit   = strtolower(substr($value, -1));

        return match ($unit) {
            'g'     => $number * 1024 * 1024 * 1024,
            'm'     => $number * 1024 * 1024,
            'k'     => $number * 1024,
            default => $number,
        };
    }
}
