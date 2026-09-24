<?php

namespace App\Console\Commands;

use App\Models\Contractor;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * استيراد كشف الشركات المسدّدة 2026 (192 شركة) من ملف Excel المدمج مع بيانات
 * العضوية والتصنيف.
 *
 * الورقة الأولى هي المصدر: سطر لكل شركة، و11 عموداً للتخصصات تحمل الدرجة مباشرة.
 * الورقة الثانية (سطر لكل تخصص) نسخة مطابقة تماماً — تم التحقق من تطابق الـ665
 * تصنيفاً بينهما، فلا حاجة لقراءتها.
 */
class ImportPaidContractors2026 extends Command
{
    protected $signature = 'contractors:import-2026
                            {file : مسار ملف الإكسل}
                            {--dry-run : عرض ما سيحدث دون أي كتابة}
                            {--status=active : حالة الشركات المستوردة (active|pending)}
                            {--membership-expires=2026-12-31 : تاريخ انتهاء اشتراك 2026، أو none لعدم إنشاء سجل اشتراك}
                            {--g-suffix : إعطاء أرقام العضوية ≥928 اللاحقة _g حسب مخطط الترقيم الجديد}';

    protected $description = 'استيراد كشف الشركات المسدّدة 2026 (بيانات الشركة + التصنيفات) إلى جدول contractors';

    /** عمود التخصص في الإكسل → [رمز المجال، رمز الاختصاص] في جداول الـ lookup */
    private const SPEC_COLUMNS = [
        9  => [20, 20],   // طرق
        10 => [30, 60],   // أبنية
        11 => [30, 100],  // صيانة أبنية
        12 => [50, 160],  // مياه ومجاري
        13 => [40, 110],  // كهروميكانيك
        14 => [40, 120],  // صيانة كهروميكانيك
        15 => [60, 200],  // أشغال عامة
        16 => [40, 140],  // كهرباء
        17 => [30, 80],   // منشآت معدنية
        18 => [50, 220],  // حفر آبار
        19 => [20, 50],   // أشغال ترابية
    ];

    /**
     * تصحيحات يدوية أقرّها الاتحاد على أرقام الهواتف المكسورة في الإكسل.
     * null = يُترك فارغاً ويُضاف لاحقاً.
     */
    private const PHONE_OVERRIDES = [
        '1'   => null,          // '59408142' ناقصة خانة ولا تُحزر
        '7'   => '599408142',   // رقم بديل يفكّ التعارض مع 79
        '24'  => '566260003',   // رقم بديل يفكّ التعارض مع 40/668
        '560' => '595122062',   // رقم بديل يفكّ التعارض مع 543 — مسجَّل باسم نور عكاشة
        '652' => '598320550',   // كانت فارغة
        '818' => null,          // '1022222294' ليس رقماً فلسطينياً
        '895' => '599880062',   // الخانة حوت رقمين سليمين — اختير هذا
    ];

    public function handle(): int
    {
        $file = $this->argument('file');
        if (! is_file($file)) {
            $this->error("الملف غير موجود: {$file}");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $status = $this->option('status');
        if (! in_array($status, ['active', 'pending'], true)) {
            $this->error('‎--status يجب أن تكون active أو pending');

            return self::FAILURE;
        }

        $this->line("قاعدة البيانات: <comment>".config('database.connections.mysql.database')."</comment>");
        $this->line('الوضع: '.($dryRun ? '<comment>محاكاة (لا كتابة)</comment>' : '<info>كتابة فعلية</info>'));
        $this->newLine();

        $rows = $this->readSheet($file);
        $this->info('قُرئ '.count($rows).' سطراً.');

        [$records, $report] = $this->buildRecords($rows);

        $this->printReport($report);

        if ($dryRun) {
            $this->newLine();
            $this->comment('محاكاة فقط — لم يُكتب شيء. أعد التشغيل بدون ‎--dry-run للتنفيذ.');

            return self::SUCCESS;
        }

        return $this->persist($records, $status);
    }

    /** قراءة الورقة الأولى كمصفوفة أسطر خام */
    private function readSheet(string $file): array
    {
        $reader = IOFactory::createReaderForFile($file);
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($file)->getSheet(0);

        $rows = [];
        foreach ($sheet->toArray(null, true, false, false) as $i => $row) {
            if ($i < 2) {
                continue; // سطر العنوان + سطر الرؤوس
            }
            if (($row[1] ?? null) === null || trim((string) $row[1]) === '') {
                continue;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * تحويل الأسطر الخام إلى سجلات جاهزة، مع حجز القيم الفريدة (هاتف/إيميل/رخصة)
     * على مبدأ "الأول يفوز" حتى لا يفشل الإدخال على قيد UNIQUE المتبقي.
     */
    private function buildRecords(array $rows): array
    {
        $records = [];
        $report = [
            'phone_prefixed' => 0, 'phone_ok' => 0, 'phone_null' => 0,
            'email_dropped' => [], 'license_dropped' => [], 'phone_dup' => [],
            'specialties' => 0, 'grade_unmapped' => [], 'grade_bare_first' => [],
        ];

        $seenEmail = [];
        $seenLicense = [];
        $seenPhone = [];

        foreach ($rows as $row) {
            $membership = $this->str($row[1]);

            [$phone, $phoneHow] = $this->normalizePhone($membership, $this->str($row[6]));
            $report[$phoneHow] = ($report[$phoneHow] ?? 0) + 1;

            if ($phone !== null) {
                if (isset($seenPhone[$phone])) {
                    $report['phone_dup'][] = [$seenPhone[$phone], $membership, $phone];
                } else {
                    $seenPhone[$phone] = $membership;
                }
            }

            // الإيميل: قد تحوي الخانة أكثر من عنوان — الأول للحقل والباقي للملاحظات
            [$email, $extraEmails] = $this->splitEmails($this->str($row[7]));
            if ($email !== null) {
                $key = mb_strtolower($email);
                if (isset($seenEmail[$key])) {
                    $report['email_dropped'][] = [$membership, $email, $seenEmail[$key]];
                    $extraEmails[] = $email;
                    $email = null;
                } else {
                    $seenEmail[$key] = $membership;
                }
            }

            $license = preg_replace('/\D/', '', $this->str($row[5])) ?: null;
            if ($license !== null) {
                if (isset($seenLicense[$license])) {
                    $report['license_dropped'][] = [$membership, $license, $seenLicense[$license]];
                    $extraEmails[] = "رقم المشتغل المرخص من الكشف: {$license} (مكرر مع عضوية {$seenLicense[$license]})";
                    $license = null;
                } else {
                    $seenLicense[$license] = $membership;
                }
            }

            $specialties = $this->buildSpecialties($row, $membership, $report);
            $report['specialties'] += count($specialties);

            $records[] = [
                'membership_number'             => $this->membershipNumber($membership),
                'name'                          => $this->str($row[2]),
                'authorized_person'             => $this->str($row[3]) ?: null,
                'authorized_person_id_number'   => preg_replace('/\D/', '', $this->str($row[4])) ?: null,
                'license_number'                => $license,
                'phone'                         => $phone,
                'email'                         => $email,
                'city'                          => $this->str($row[8]) ?: null,
                'specialties'                   => $specialties,
                'classification'                => $specialties[0]['classification'] ?? null,
                'field_lk_type'                 => $specialties[0]['field_lk_type'] ?? null,
                'specialization_lk_type'        => $specialties[0]['specialization_lk_type'] ?? null,
                'classification_decision_number' => $this->normalizeSession($this->str($row[20])),
                'classification_decision_date'  => $this->normalizeDate($row[21] ?? null),
                'notes'                         => $extraEmails ? implode(' | ', $extraEmails) : null,
            ];
        }

        return [$records, $report];
    }

    /**
     * توحيد رقم الجوال.
     *
     * أرقام الـ7 خانات في الكشف هي جوالات فقدت البادئة "59" أثناء التفريغ — تأكّد
     * ذلك بمطابقة توزيع خانتها الأولى {2,4,5,7,8,9} مع الخانة الثالثة للجوالات
     * التسعية المؤكدة، وبغياب {0,1,3,6} من الطرفين. البادئة 56 (وطنية) سليمة وتُترك.
     *
     * @return array{0: ?string, 1: string}
     */
    private function normalizePhone(string $membership, string $raw): array
    {
        if (array_key_exists($membership, self::PHONE_OVERRIDES)) {
            $value = self::PHONE_OVERRIDES[$membership];

            return [$value, $value === null ? 'phone_null' : 'phone_ok'];
        }

        $d = preg_replace('/\D/', '', $raw);
        if ($d === '') {
            return [null, 'phone_null'];
        }
        if (strlen($d) >= 12 && str_starts_with($d, '00970')) {
            $d = substr($d, 5);
        } elseif (strlen($d) >= 12 && str_starts_with($d, '970')) {
            $d = substr($d, 3);
        }
        if (strlen($d) === 10 && str_starts_with($d, '0')) {
            $d = substr($d, 1);
        }
        if (strlen($d) === 7) {
            return ['59'.$d, 'phone_prefixed'];
        }
        if (strlen($d) === 9 && (str_starts_with($d, '59') || str_starts_with($d, '56'))) {
            return [$d, 'phone_ok'];
        }

        return [null, 'phone_null'];
    }

    /** @return array{0: ?string, 1: array<string>} */
    private function splitEmails(string $raw): array
    {
        if ($raw === '') {
            return [null, []];
        }
        preg_match_all(
            '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/',
            str_replace(',', '.', $raw),
            $m
        );
        $found = array_values(array_unique($m[0] ?? []));
        if ($found === []) {
            return [null, []];
        }

        return [array_shift($found), $found];
    }

    private function buildSpecialties(array $row, string $membership, array &$report): array
    {
        $out = [];
        foreach (self::SPEC_COLUMNS as $col => [$fieldCode, $specCode]) {
            $raw = $this->str($row[$col] ?? null);
            if ($raw === '') {
                continue;
            }
            $grade = $this->normalizeGrade($raw, $fieldCode);
            if ($grade === null) {
                $report['grade_unmapped'][] = [$membership, $raw, $fieldCode];

                continue;
            }
            if ($this->isBareFirstGrade($raw)) {
                $report['grade_bare_first'][] = [$membership, $fieldCode, $specCode];
            }
            $out[] = [
                'field_lk_type'          => $fieldCode,
                'specialization_lk_type' => $specCode,
                'classification'         => $grade,
            ];
        }

        return $out;
    }

    private function isBareFirstGrade(string $raw): bool
    {
        $raw = preg_replace('/\s+/u', ' ', trim($raw));

        return $raw === 'أولى' || $raw === 'اولى';
    }

    /**
     * توحيد الدرجة إلى الرموز السبعة المعتمدة.
     *
     * "أولى" المجردة (77 خانة) ليست اختصاراً لـ"اولى ب" بل درجة قائمة بذاتها: الاتحاد
     * يميّز أ/ب لتخصصات الأبنية والطرق والأشغال العامة فقط، وما عداها يأخذ "اولى"
     * بلا تمييز. الكشف يؤكد القاعدة — لا عمود واحد يخلط الصيغتين: عمودا "طرق"
     * و"أبنية" يكتبان أ/ب حصراً (98 خانة) ولا تظهر فيهما "أولى" مجردة قط، وبقية
     * الأعمدة السبعة تكتب "أولى" مجردة حصراً ولا تظهر فيها أ/ب قط.
     *
     * الدرجة مضافة إلى contractor_grades و grade_fees في هجرة
     * 2026_09_24_130000_add_awla_grade_to_lookups — ووجودها في الجدولين معاً شرط،
     * وإلا صارت رسوم 44 شركة unresolvable.
     */
    private function normalizeGrade(string $raw, int $fieldCode): ?string
    {
        $map = [
            'أولى أ' => 'اولى أ',
            'اولى أ' => 'اولى أ',
            'أولى ب' => 'اولى ب',
            'اولى ب' => 'اولى ب',
            'ثانية'  => 'ثانية',
            'ثالثة'  => 'ثالثة',
            'رابعة'  => 'رابعة',
            'خامسة'  => 'خامسة',
        ];

        $raw = preg_replace('/\s+/u', ' ', trim($raw));

        if (isset($map[$raw])) {
            return $map[$raw];
        }

        if ($this->isBareFirstGrade($raw)) {
            return 'اولى';
        }

        return null;
    }

    /** "01/2021" و"1-2023" → "1/2021" و"1/2023" */
    private function normalizeSession(string $raw): ?string
    {
        if ($raw === '') {
            return null;
        }
        $raw = str_replace(['-', '\\'], '/', $raw);
        if (preg_match('/^0*(\d+)\s*\/\s*(\d{4})$/', $raw, $m)) {
            return $m[1].'/'.$m[2];
        }

        return $raw;
    }

    private function normalizeDate(mixed $value): ?string
    {
        $value = $this->str($value);
        if ($value === '') {
            return null;
        }
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /** أرقام ≥928 تتبع مخطط {n}_g الجديد عند تفعيل الخيار */
    private function membershipNumber(string $raw): string
    {
        if ($this->option('g-suffix') && ctype_digit($raw) && (int) $raw >= 928) {
            return $raw.'_g';
        }

        return $raw;
    }

    private function str(mixed $v): string
    {
        return $v === null ? '' : trim((string) $v);
    }

    private function printReport(array $r): void
    {
        $this->newLine();
        $this->line('<comment>الهواتف</comment>');
        $this->line('  أضيفت لها البادئة 59 : '.($r['phone_prefixed'] ?? 0));
        $this->line('  سليمة كما هي          : '.($r['phone_ok'] ?? 0));
        $this->line('  تُركت فارغة            : '.($r['phone_null'] ?? 0));

        if ($r['phone_dup']) {
            $this->newLine();
            $this->line('<comment>أرقام مشتركة بين أكثر من شركة ('.count($r['phone_dup']).') — مسموحة بعد رفع قيد UNIQUE</comment>');
            foreach ($r['phone_dup'] as [$first, $second, $phone]) {
                $this->line("  {$phone} : عضوية {$first} و {$second}");
            }
        }

        if ($r['email_dropped']) {
            $this->newLine();
            $this->line('<comment>إيميلات مكررة نُقلت للملاحظات ('.count($r['email_dropped']).')</comment>');
            foreach ($r['email_dropped'] as [$mb, $email, $owner]) {
                $this->line("  عضوية {$mb} : {$email} (محجوز لعضوية {$owner})");
            }
        }

        if ($r['license_dropped']) {
            $this->newLine();
            $this->line('<comment>أرقام مشتغل مرخص مكررة نُقلت للملاحظات ('.count($r['license_dropped']).')</comment>');
            foreach ($r['license_dropped'] as [$mb, $lic, $owner]) {
                $this->line("  عضوية {$mb} : {$lic} (محجوز لعضوية {$owner})");
            }
        }

        $this->newLine();
        $this->line('<comment>التصنيفات</comment>');
        $this->line('  إجمالي التخصصات المبنية : '.$r['specialties']);

        $this->line('  درجة «اولى» (بلا تمييز أ/ب) : '.count($r['grade_bare_first']));

        if ($r['grade_unmapped']) {
            $this->newLine();
            $this->error('درجات لم تُفهم ('.count($r['grade_unmapped']).') — ستُستبعد:');
            foreach ($r['grade_unmapped'] as [$mb, $raw, $field]) {
                $this->line("  عضوية {$mb} : «{$raw}» في مجال {$field}");
            }
        }
    }

    private function persist(array $records, string $status): int
    {
        $expires = $this->option('membership-expires');
        $withMembership = $expires !== 'none';

        $existing = Contractor::whereIn('membership_number', array_column($records, 'membership_number'))
            ->pluck('membership_number')
            ->all();

        if ($existing !== []) {
            $this->error('توجد أرقام عضوية مسجّلة مسبقاً ('.count($existing).') — أوقفت الاستيراد لتفادي الازدواج:');
            $this->line('  '.implode(', ', array_slice($existing, 0, 20)).(count($existing) > 20 ? ' …' : ''));

            return self::FAILURE;
        }

        $created = 0;

        DB::transaction(function () use ($records, $status, $withMembership, $expires, &$created) {
            foreach ($records as $data) {
                $contractor = Contractor::create($data + [
                    'status'            => $status,
                    'is_frozen'         => false,
                    'profile_completed' => false,
                ]);

                if ($withMembership) {
                    DB::table('memberships')->insert([
                        'contractor_id' => $contractor->id,
                        'type'          => 'renewal',
                        'status'        => 'active',
                        'starts_at'     => Carbon::parse($expires)->startOfYear()->toDateString(),
                        'expires_at'    => Carbon::parse($expires)->toDateString(),
                        'amount'        => 0,
                        'notes'         => 'مستورد من كشف الشركات المسدّدة 2026',
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                }

                $created++;
            }
        });

        $this->newLine();
        $this->info("تم إنشاء {$created} شركة بحالة «{$status}»".($withMembership ? " مع سجل اشتراك ينتهي {$expires}." : '.'));

        return self::SUCCESS;
    }
}
