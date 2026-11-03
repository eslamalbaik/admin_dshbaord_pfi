<?php

namespace App\Rules;

use App\Models\Contractor;
use App\Support\UploadLimits;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * صيغة مستند المقاول: pdf/doc/docx/jpg/jpeg/png حسب محتوى الملف (UploadLimits::detectDocumentExtension).
 *
 * بديل mimes: كانت ترفض PDF صحيحاً من تطبيقات البنوك (كشف حساب) لأن fileinfo لا يتعرف
 * عليه، فتظهر في التطبيق «صيغة ملف ... غير مسموحة» لملف PDF فعلاً.
 */
class ContractorDocument implements ValidationRule
{
    private const BLOCKED_CLIENT_EXTENSIONS = ['php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'pht'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            return; // قاعدة file تتولى هذه الحالة برسالتها
        }

        $label  = Contractor::DOCUMENT_LABELS[$attribute] ?? $attribute;
        $types  = implode('، ', UploadLimits::ALLOWED_EXTENSIONS);
        $client = strtolower($value->getClientOriginalExtension());

        if ($value->getSize() === 0) {
            $this->log($attribute, $value, 'empty');
            $fail("ملف «{$label}» فارغ (0 بايت) — أعد اختياره من الجهاز وحاول مرة أخرى.");

            return;
        }

        $detected = in_array($client, self::BLOCKED_CLIENT_EXTENSIONS, true)
            ? null
            : UploadLimits::detectDocumentExtension($value);

        if ($detected !== null) {
            return;
        }

        $this->log($attribute, $value, 'type');
        $kind = $client !== '' ? $client : ($value->guessExtension() ?: $value->getMimeType());
        $fail("صيغة ملف «{$label}» غير مسموحة (الملف المرفوع: {$kind}) — الصيغ المسموحة: {$types}.");
    }

    private function log(string $attribute, UploadedFile $file, string $reason): void
    {
        Log::warning('contractor_document_rejected', [
            'field'    => $attribute,
            'reason'   => $reason,
            'name'     => $file->getClientOriginalName(),
            'client_mime' => $file->getClientMimeType(),
            'detected_mime' => $file->getMimeType(),
            'size'     => $file->getSize(),
            'head_hex' => bin2hex((string) @file_get_contents($file->getRealPath(), false, null, 0, 16)),
        ]);
    }
}
