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

    /** الامتدادات المسموح بها لمستندات المقاول — لوحة الأدمن وتعديل الملف من التطبيق (documentRule). */
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

    /**
     * قاعدة التحقق الموحّدة لمستندات المقاول الرسمية — لوحة الأدمن وتعديل الملف من
     * التطبيق معاً. كان التطبيق (UpdateFullProfileRequest) على mimes:pdf,jpg,jpeg,png
     * و max:10240 ثابتين، فيُرفض ملف Word يقبله الأدمن.
     *
     * الصيغة تُفحص بـ ContractorDocument لا بـ mimes: قاعدة mimes تعتمد على تخمين
     * fileinfo وحده، فترفض ملفات صحيحة يقرؤها fileinfo على أنها octet-stream أو zip
     * (PDF مولَّد من تطبيقات البنوك وفي أوله بايتات زائدة قبل %PDF، أو docx).
     */
    public static function documentRule(): array
    {
        return ['nullable', 'file', new \App\Rules\ContractorDocument, 'max:' . self::maxFileKb()];
    }

    /**
     * الصيغة الحقيقية لملف مستند من بين ALLOWED_EXTENSIONS، أو null إن لم يكن منها.
     * أولاً تخمين fileinfo، ثم فحص التوقيع (magic bytes) للحالات التي يخطئ فيها.
     */
    public static function detectDocumentExtension(\Illuminate\Http\UploadedFile $file): ?string
    {
        $guess = strtolower((string) $file->guessExtension());
        if ($guess === 'jpe') {
            $guess = 'jpg';
        }
        if (in_array($guess, self::ALLOWED_EXTENSIONS, true)) {
            return $guess;
        }

        $path = $file->getRealPath();
        if (! $path || ! is_readable($path)) {
            return null;
        }

        $head = (string) @file_get_contents($path, false, null, 0, 1024);
        if ($head === '') {
            return null;
        }

        if (str_starts_with($head, "\xFF\xD8\xFF")) {
            return 'jpg';
        }
        if (str_starts_with($head, "\x89PNG\r\n\x1A\n")) {
            return 'png';
        }
        // مولّدات PDF في بعض البنوك تضع بايتات قبل الترويسة — المواصفة تسمح بها ضمن أول 1024 بايت.
        if (str_contains($head, '%PDF-')) {
            return 'pdf';
        }
        if (str_starts_with($head, "PK\x03\x04") && class_exists(\ZipArchive::class)) {
            $zip = new \ZipArchive;
            if ($zip->open($path) === true) {
                $isWord = $zip->locateName('word/document.xml') !== false;
                $zip->close();
                if ($isWord) {
                    return 'docx';
                }
            }
        }
        // حاوية OLE: Word 97-2003 (.doc) أو docx محمي بكلمة سر — نعتمد امتداد العميل للتمييز.
        if (str_starts_with($head, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1")) {
            $client = strtolower($file->getClientOriginalExtension());
            if (in_array($client, ['doc', 'docx'], true)) {
                return $client;
            }
        }

        return null;
    }

    /**
     * يحفظ مستنداً بامتداده الحقيقي. store() العادية تبني الاسم من guessExtension، فملف
     * قُبل بفحص التوقيع كان سيُحفظ بلا امتداد ولا يفتحه المتصفح.
     */
    public static function storeDocument(\Illuminate\Http\UploadedFile $file, string $dir, string $disk = 'public'): string|false
    {
        $ext = self::detectDocumentExtension($file);
        if ($ext === null) {
            return $file->store($dir, $disk);
        }

        return $file->storeAs($dir, \Illuminate\Support\Str::random(40) . '.' . $ext, $disk);
    }

    /**
     * رسائل الصيغة والحجم لكل حقل مستند، بالاسم العربي للمستند وبالميجابايت —
     * بدونها كانت الرسالة "يجب أن يكون cr file ملفاً من نوع..." والحجم بالكيلوبايت.
     *
     * @param  array<string,string>  $labels  مفتاح الحقل => اسمه العربي
     */
    public static function documentMessages(array $labels): array
    {
        $types = implode('، ', self::ALLOWED_EXTENSIONS);
        $maxMb = round(self::maxFileKb() / 1024, 1);
        $maxMb = $maxMb == (int) $maxMb ? (int) $maxMb : $maxMb;

        $messages = [];
        foreach ($labels as $field => $label) {
            $messages["{$field}.mimes"] = "صيغة ملف «{$label}» غير مسموحة — الصيغ المسموحة: {$types}.";
            $messages["{$field}.max"]   = "حجم ملف «{$label}» أكبر من المسموح — الحد الأقصى {$maxMb} ميجابايت للملف.";
            $messages["{$field}.file"]  = "تعذّر رفع ملف «{$label}» — الحد الأقصى {$maxMb} ميجابايت والصيغ المسموحة: {$types}.";
        }

        return $messages;
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
