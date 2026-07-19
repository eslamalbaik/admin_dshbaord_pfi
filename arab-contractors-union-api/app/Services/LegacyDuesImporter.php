<?php

namespace App\Services;

use App\Models\Contractor;
use App\Models\ContractorDue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * استيراد كشف الذمم القديم (شركات المقاولات وتصنيفاتهم) كذمم legacy_import.
 * مشترك بين أمر dues:import-legacy وواجهة الرفع في لوحة التحكم.
 *
 * بنية الشيت (أعمدة ثابتة):
 *  A م | B اسم الشركة | C المفوض | D رقم العضوية | E المشتغل المرخص
 *  F المجال | G التخصص | H الدرجة | I الرسم الأساسي | J المعامل | K الرسم المحتسب
 *  L "2020 *50%" | M "2021*70%" | N سنة آخر جلسة | O رقم آخر جلسة | P تاريخ الجلسة
 * لكل شركة عدة صفوف تخصصات يتبعها صف "المجموع".
 */
class LegacyDuesImporter
{
    /**
     * @return array{companies: array, matched: array, unmatched: array,
     *               total_sheet_jod: float, total_matched_jod: float}
     */
    public function analyze(string $file, ?string $sheetName = null): array
    {
        // قراءة البيانات فقط (بدون أنماط وتنسيقات) — الملفات المنسّقة بكثافة
        // كانت تتجاوز مهلة التنفيذ عند قراءة الأنماط كاملة
        @set_time_limit(300);

        $reader = IOFactory::createReaderForFile($file);
        $reader->setReadDataOnly(true);
        if ($sheetName) {
            $reader->setLoadSheetsOnly([$sheetName]);
        }

        $spreadsheet = $reader->load($file);
        $sheet       = $sheetName
            ? $spreadsheet->getSheetByName($sheetName)
            : $spreadsheet->getSheet(0);

        if (! $sheet) {
            throw new \RuntimeException('الشيت المطلوب غير موجود في الملف.');
        }

        $companies = $this->parseCompanies($sheet->toArray(null, true, false, false));

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        $matched   = [];
        $unmatched = [];

        foreach ($companies as $company) {
            $number     = $company['membership_number'];
            $contractor = $number === '' ? null : Contractor::where('membership_number', $number)
                ->orWhere('membership_number', (string) (int) $number)
                ->first();

            // مطابقة ثانية برقم المشتغل المرخص (السجل التجاري) — أرقام العضوية
            // القديمة قد تكون بصيغ مختلفة في قاعدة البيانات
            if (! $contractor && ! empty($company['licensed_number'])) {
                $contractor = Contractor::where('commercial_register', $company['licensed_number'])->first();
            }

            if ($contractor) {
                $company['contractor_id']   = $contractor->id;
                $company['contractor_name'] = $contractor->name;
                $matched[] = $company;
            } else {
                $unmatched[] = $company;
            }
        }

        return [
            'companies'         => $companies,
            'matched'           => $matched,
            'unmatched'         => $unmatched,
            'total_sheet_jod'   => round(collect($companies)->flatMap(fn ($c) => $c['dues'])->sum('amount_jod'), 2),
            'total_matched_jod' => round(collect($matched)->flatMap(fn ($c) => $c['dues'])->sum('amount_jod'), 2),
        ];
    }

    public function hasPreviousImport(): bool
    {
        return ContractorDue::where('source', 'legacy_import')->exists();
    }

    /**
     * تنفيذ الاستيراد الفعلي.
     * $force: حذف صفوف legacy_import السابقة وإعادة الاستيراد.
     * $createMissing: إنشاء الشركات غير الموجودة كمقاولين جدد من بيانات الكشف.
     *
     * @return array{dues: int, contractors_created: int}
     */
    public function import(
        array $matched,
        array $unmatched = [],
        bool $force = false,
        ?int $userId = null,
        bool $createMissing = false,
    ): array {
        return DB::transaction(function () use ($matched, $unmatched, $force, $userId, $createMissing) {
            if ($force) {
                ContractorDue::where('source', 'legacy_import')->forceDelete();
            }

            $contractorsCreated = 0;

            // إنشاء الشركات غير الموجودة من بيانات الكشف ثم ضمّها للمطابَقة
            if ($createMissing) {
                foreach ($unmatched as $company) {
                    // حماية أخيرة من التكرار: قد يكون الرقم/السجل ظهر أثناء نفس الاستيراد
                    $contractor = null;
                    if (! empty($company['licensed_number'])) {
                        $contractor = Contractor::where('commercial_register', $company['licensed_number'])->first();
                    }
                    if (! $contractor && $company['membership_number'] !== '') {
                        $contractor = Contractor::where('membership_number', $company['membership_number'])->first();
                    }

                    if (! $contractor) {
                        $contractor = Contractor::create([
                            'name'                => $company['name'],
                            'membership_number'   => $company['membership_number'] !== '' ? $company['membership_number'] : null,
                            'authorized_person'   => $company['authorized_person'] ?? null,
                            'commercial_register' => $company['licensed_number'] ?? null,
                            'trade'               => $company['first_trade'] ?? null,
                            'classification'      => $company['first_grade'] ?? null,
                            'status'              => 'active',
                        ]);
                        $contractorsCreated++;
                    }

                    $company['contractor_id'] = $contractor->id;
                    $matched[] = $company;
                }
            }

            $created = 0;
            foreach ($matched as $company) {
                foreach ($company['dues'] as $due) {
                    ContractorDue::create([
                        'contractor_id' => $company['contractor_id'],
                        'year'          => $due['year'],
                        'period'        => $company['last_session_number'],
                        'description'   => $due['description'],
                        'amount_jod'    => $due['amount_jod'],
                        'status'        => 'unpaid',
                        'source'        => 'legacy_import',
                        'notes'         => $company['notes'],
                        'created_by'    => $userId,
                    ]);
                    $created++;
                }
            }

            return ['dues' => $created, 'contractors_created' => $contractorsCreated];
        });
    }

    public function logReport(array $analysis, bool $dryRun, ?int $userId = null): void
    {
        Log::channel('import')->info('dues.import_report', [
            'user_id'   => $userId,
            'total'     => count($analysis['companies']),
            'matched'   => count($analysis['matched']),
            'unmatched' => collect($analysis['unmatched'])->map(fn ($c) => [
                'seq'               => $c['seq'],
                'membership_number' => $c['membership_number'],
                'name'              => $c['name'],
            ])->all(),
            'total_jod' => $analysis['total_matched_jod'],
            'dry_run'   => $dryRun,
        ]);
    }

    /**
     * تجميع صفوف الشيت لكل شركة (الاسم ورقم العضوية يُرحَّلان من الصف الأول).
     */
    private function parseCompanies(array $rows): array
    {
        $companies = [];
        $current   = null;

        foreach ($rows as $i => $row) {
            if ($i === 0) {
                continue; // صف العناوين
            }

            $name = trim((string) ($row[1] ?? ''));

            // صف "المجموع" — إجمالي الرسم السنوي للشركة الحالية
            if ($name === 'المجموع') {
                if ($current) {
                    $current['annual_total'] = $this->number($row[10] ?? null);
                    $companies[] = $this->finalizeCompany($current);
                    $current = null;
                }

                continue;
            }

            // صف بداية شركة جديدة (فيه رقم تسلسلي واسم)
            if ($name !== '' && $this->number($row[0] ?? null) !== null) {
                if ($current) {
                    // شركة سابقة بلا صف مجموع
                    $companies[] = $this->finalizeCompany($current);
                }

                $current = [
                    'seq'                 => (int) $this->number($row[0]),
                    'name'                => $name,
                    'authorized_person'   => trim((string) ($row[2] ?? '')) ?: null,
                    'membership_number'   => $this->normalizeDigits(trim((string) ($row[3] ?? ''))),
                    'licensed_number'     => $this->normalizeDigits(trim((string) ($row[4] ?? ''))) ?: null,
                    'first_trade'         => null,
                    'first_grade'         => null,
                    'fields'              => [],
                    'due_2020'            => null,
                    'due_2021'            => null,
                    'annual_total'        => null,
                    'last_session_year'   => $this->normalizeDigits(trim((string) ($row[13] ?? ''))),
                    'last_session_number' => $this->normalizeDigits(trim((string) ($row[14] ?? ''))) ?: null,
                    'last_session_date'   => $row[15] ?? null,
                ];
            }

            if (! $current) {
                continue;
            }

            // صف تخصص (مجال/تخصص/درجة)
            $field = trim((string) ($row[5] ?? ''));
            if ($field !== '') {
                $grade = trim((string) ($row[7] ?? ''));
                $current['fields'][] = $field . ' / ' . trim((string) ($row[6] ?? '')) . ' — ' . $grade;

                // أول مجال/درجة يُعتمدان كتصنيف رئيسي عند إنشاء المقاول
                if ($current['first_trade'] === null) {
                    $current['first_trade'] = $field;
                    $current['first_grade'] = $grade ?: null;
                }
            }

            // أعمدة سنوات الخصم قد تظهر في أي صف من صفوف الشركة
            foreach ([11 => 'due_2020', 12 => 'due_2021'] as $col => $key) {
                $value = $this->number($row[$col] ?? null);
                if ($value !== null && $value > 0 && $current[$key] === null) {
                    $current[$key] = round($value, 2);
                }
            }
        }

        if ($current) {
            $companies[] = $this->finalizeCompany($current);
        }

        return $companies;
    }

    private function finalizeCompany(array $c): array
    {
        $notes = 'استيراد من كشف الأرشفة الإلكترونية. التصنيفات: ' . implode('؛ ', $c['fields']);
        if ($c['annual_total'] !== null) {
            $notes .= '. إجمالي الرسم السنوي: ' . $c['annual_total'] . ' دينار';
        }
        if ($c['last_session_year'] || $c['last_session_number']) {
            $notes .= '. آخر جلسة تصنيف: ' . trim($c['last_session_number'] . ' / ' . $c['last_session_year'], ' /');
        }

        $dues = [];
        if ($c['due_2020'] !== null) {
            $dues[] = [
                'year'        => 2020,
                'description' => 'رسوم اشتراك سنة 2020 (بعد خصم 50%)',
                'amount_jod'  => $c['due_2020'],
            ];
        }
        if ($c['due_2021'] !== null) {
            $dues[] = [
                'year'        => 2021,
                'description' => 'رسوم اشتراك سنة 2021 (بعد خصم 30%)',
                'amount_jod'  => $c['due_2021'],
            ];
        }

        return $c + ['dues' => $dues, 'notes' => $notes];
    }

    /** تحويل الأرقام العربية-الهندية إلى لاتينية */
    private function normalizeDigits(string $value): string
    {
        return strtr($value, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }

    private function number($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = $this->normalizeDigits((string) $value);

        return is_numeric($value) ? (float) $value : null;
    }
}
