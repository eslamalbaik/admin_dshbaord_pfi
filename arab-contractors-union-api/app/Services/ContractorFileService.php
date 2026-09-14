<?php

namespace App\Services;

use App\Models\Contractor;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * خدمة ملفات المقاول — رفع، حذف، وتحميل الملفات المرفقة بالملف الشخصي.
 * مصدر واحد لقائمة الحقول ومساراتها (بدل التكرار في الكنترولر).
 *
 * تحسين أمني: الملف الجديد يُرفع أولاً ثم يُحذف القديم (upload-then-delete).
 */
class ContractorFileService
{
    /**
     * الحقول المسموح رفعها ومسارات تخزينها.
     */
    public const FILE_FIELDS = [
        'cr_file'                        => 'contractors/cr',
        'id_file'                        => 'contractors/id',
        'lease_or_ownership_contract'    => 'contractors/leases',
        'company_approval_letter'        => 'contractors/approvals',
        'municipal_license'              => 'contractors/licenses',
        'company_register'               => 'contractors/registers',
        'articles_of_association'        => 'contractors/articles',
        'internal_bylaws'                => 'contractors/bylaws',
        'bank_dealing_letter'            => 'contractors/bank_letters',
        'secretary_contract'             => 'contractors/secretary_contracts',
        'full_time_engineer_certificate' => 'contractors/engineer_certs',
        'partners_ids'                   => 'contractors/partners_ids',
        'authorization_letter'           => 'contractors/authorization_letters',
        'authorized_signature'           => 'contractors/signatures',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    //  رفع ملفات الملف الشخصي
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * يرفع الملفات المرفقة بالطلب ويُحدِّث مصفوفة $validated بالمسارات الجديدة.
     * يحذف الملف القديم فقط بعد نجاح الرفع (upload-then-delete).
     */
    public function uploadProfileFiles(Request $request, array &$validated, Contractor $contractor): void
    {
        foreach (self::FILE_FIELDS as $field => $path) {
            if ($request->hasFile($field)) {
                // 1. ارفع الجديد أولاً
                $newPath = $request->file($field)->store($path, 'public');

                // 2. تأكد من النجاح ثم احذف القديم
                if ($newPath && $contractor->$field) {
                    Storage::disk('public')->delete($contractor->$field);
                }

                $validated[$field] = $newPath;
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  رفع/استبدال الشعار
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * يرفع شعار الشركة الجديد ويحذف القديم بعد نجاح الرفع.
     *
     * @return string المسار النسبي للشعار الجديد
     */
    public function uploadLogo(UploadedFile $file, Contractor $contractor): string
    {
        $newPath = $file->store('contractors/logos', 'public');

        if ($newPath && $contractor->logo) {
            Storage::disk('public')->delete($contractor->logo);
        }

        return $newPath;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  تحميل ملف
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @return StreamedResponse|null  null إذا الملف غير موجود
     */
    public function downloadFile(Contractor $contractor, string $field): ?StreamedResponse
    {
        if (! in_array($field, $this->allowedDownloadFields())) {
            return null;
        }

        $filePath = $contractor->$field;

        if (! $filePath || ! Storage::disk('public')->exists($filePath)) {
            return null;
        }

        return Storage::disk('public')->download($filePath);
    }

    /**
     * قائمة الحقول المسموح تحميلها.
     */
    public function allowedDownloadFields(): array
    {
        return array_keys(self::FILE_FIELDS);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  روابط الملفات — لتنسيق الاستجابة
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * يُرجع مصفوفة بروابط الملفات العامة (field_url => url|null).
     */
    public function fileUrls(Contractor $contractor): array
    {
        $urls = [];

        foreach (array_keys(self::FILE_FIELDS) as $field) {
            $urls[$field . '_url'] = $contractor->$field
                ? Storage::disk('public')->url($contractor->$field)
                : null;
        }

        return $urls;
    }
}
