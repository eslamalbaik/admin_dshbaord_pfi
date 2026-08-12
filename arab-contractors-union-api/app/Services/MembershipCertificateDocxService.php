<?php

namespace App\Services;

use App\Models\CertificateRequest;
use App\Support\ContractorLookups;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;

class MembershipCertificateDocxService
{
    public function generate(CertificateRequest $certRequest, array $overrides = []): string
    {
        $contractor = $certRequest->contractor;
        $membership = $contractor->activeMembership;

        $serial   = 'MC-' . str_pad((string) $certRequest->id, 6, '0', STR_PAD_LEFT);
        $decisionDate = $overrides['decision_date'] ?? ($contractor->classification_decision_date?->format('Y-m-d') ?? '—');
        $decisionNumber = $overrides['decision_number'] ?? ($contractor->classification_decision_number ?: '—');
        $validUntil = $membership?->expires_at?->format('Y-m-d') ?? now()->addYear()->format('Y-m-d');

        $address = $overrides['address'] ?? ($contractor->city ?: $contractor->governorate?->name ?: $contractor->address ?: 'غزة');

        // القالب ما عاد يكتب "شركة/" ثابتة — نبنيها هون عشان ما تتكرر لو اسم
        // المقاول أصلاً حامل كلمة "شركة" (مثال: "شركة مشتهى للمقاولات")
        $companyLabel = str_contains($contractor->name, 'شركة')
            ? $contractor->name
            : "شركة/{$contractor->name}";

        $presidentName  = \App\Models\Setting::get('union_president_name', 'المهندس/ سهيل هاشم السقا');
        $presidentTitle1 = \App\Models\Setting::get('union_president_title1', 'نقيب المقاولين الفلسطينيين');
        $presidentTitle2 = \App\Models\Setting::get('union_president_title2', 'النائب الأول لرئيس الاتحاد');

        $templatePath = resource_path('templates/membership_certificate.docx');
        if (!file_exists($templatePath)) {
            throw new \Exception("Template not found: {$templatePath}");
        }

        $tp = new TemplateProcessor($templatePath);
        
        $tp->setValue('serial', $serial);
        $tp->setValue('issue_date', now()->format('Y-m-d'));
        $tp->setValue('company_name', $companyLabel);
        $tp->setValue('address', $address);
        $tp->setValue('membership_number', $contractor->membership_number);
        $tp->setValue('decision_number', $decisionNumber);
        $tp->setValue('decision_date', $decisionDate);
        $tp->setValue('valid_until', $validUntil);
        $tp->setValue('president_name', $presidentName);
        $tp->setValue('president_title1', $presidentTitle1);
        $tp->setValue('president_title2', $presidentTitle2);

        // Map specialties
        $specialties = [];
        foreach (ContractorLookups::buildFieldsTree($contractor->specialties) as $field) {
            foreach ($field['specializations'] as $spec) {
                $specialties[] = [
                    'spec_field' => $field['field_name'],
                    'spec_name'  => $spec['spec_name'],
                    'spec_grade' => $spec['grade_label'] ?? $spec['grade'] ?? '—',
                ];
            }
        }
        
        if (empty($specialties)) {
            $specialties[] = [
                'spec_field' => '—',
                'spec_name'  => 'لا يوجد تخصصات مسجلة',
                'spec_grade' => '—',
            ];
        }

        $tp->cloneRowAndSetValues('spec_field', $specialties);

        $docxPath = "certificates/membership/{$serial}.docx";
        $fullDocxPath = Storage::disk('public')->path($docxPath);

        $dir = dirname($fullDocxPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $tp->saveAs($fullDocxPath);

        // يُسلَّم PDF للمقاول — DOCX الوسيط يُحذف بعد التحويل (أو عند الفشل). التحويل
        // عبر LibreOffice (وليس PHPWord/mPDF) لأنه المحرك الوحيد اللي بيحافظ على
        // الهيدر/الفوتر الرسمي وتشكيل الحروف العربية المطابق تماماً لمخرجات Word.
        $pdfPath = "certificates/membership/{$serial}.pdf";

        try {
            $this->convertToPdf($fullDocxPath, dirname($fullDocxPath));
        } finally {
            if (file_exists($fullDocxPath)) {
                unlink($fullDocxPath);
            }
        }

        return $pdfPath;
    }

    private function convertToPdf(string $docxPath, string $outDir): void
    {
        $binary = config('services.libreoffice.binary');

        // Symfony Process بيحتاج مجلد مؤقت قابل للكتابة عشان يلتقط مخرجات العملية على
        // ويندوز (WindowsPipes) — تحت php artisan serve المدمج sys_get_temp_dir() بيرجع
        // C:\Windows (بدون صلاحية كتابة) بدل TEMP الحقيقي، فيفشل قبل ما يوصل لـ soffice
        // أصلاً. نجبره على مجلد مضمون الكتابة.
        $tmpDir = storage_path('app/tmp');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }
        putenv("TEMP={$tmpDir}");
        putenv("TMP={$tmpDir}");

        $profileDir = storage_path('app/lo-profile-' . uniqid());
        $profileUri = 'file:///' . str_replace('\\', '/', $profileDir);

        $stdoutFile = $tmpDir . DIRECTORY_SEPARATOR . 'lo-out-' . uniqid() . '.log';
        $stderrFile = $tmpDir . DIRECTORY_SEPARATOR . 'lo-err-' . uniqid() . '.log';

        $cmd = [
            $binary, '--headless', '--norestore',
            "-env:UserInstallation={$profileUri}",
            '--convert-to', 'pdf', '--outdir', $outDir, $docxPath,
        ];

        // proc_open مباشر مع تحويل المخرجات لملفات — بيتخطى آلية Symfony Process
        // لالتقاط المخرجات عبر ملفات مؤقتة (WindowsPipes) اللي بتفشل بصمت بنفس السياق
        $process = proc_open($cmd, [1 => ['file', $stdoutFile, 'w'], 2 => ['file', $stderrFile, 'w']], $pipes);
        $exitCode = is_resource($process) ? proc_close($process) : -1;

        $output = is_file($stdoutFile) ? file_get_contents($stdoutFile) : '';
        $errorOutput = is_file($stderrFile) ? file_get_contents($stderrFile) : '';
        @unlink($stdoutFile);
        @unlink($stderrFile);

        File::deleteDirectory($profileDir);

        if ($exitCode === 0 && file_exists(preg_replace('/\.docx$/', '.pdf', $docxPath))) {
            return;
        }

        Log::error('LibreOffice PDF conversion failed', [
            'exit_code'    => $exitCode,
            'output'       => $output,
            'error_output' => $errorOutput,
            'command'      => $cmd,
        ]);

        throw new \Exception("فشل تحويل الشهادة إلى PDF عبر LibreOffice: {$errorOutput}");
    }
}
