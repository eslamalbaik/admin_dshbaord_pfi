<?php

namespace App\Console\Commands;

use App\Services\LegacyDuesImporter;
use Illuminate\Console\Command;

/**
 * استيراد كشف الذمم القديم من ملف إكسل — المنطق في App\Services\LegacyDuesImporter.
 */
class ImportLegacyDues extends Command
{
    protected $signature = 'dues:import-legacy
                            {file : مسار ملف الإكسل}
                            {--sheet= : اسم الشيت (افتراضياً الأول)}
                            {--dry-run : عرض التقرير دون كتابة}
                            {--force : حذف استيراد سابق وإعادة الاستيراد}
                            {--create-missing : إنشاء الشركات غير الموجودة كمقاولين جدد}';

    protected $description = 'استيراد كشف رسوم المقاولين القديم كذمم مالية بالدينار الأردني';

    public function handle(LegacyDuesImporter $importer): int
    {
        $file = $this->argument('file');

        if (! is_file($file)) {
            $this->error("الملف غير موجود: {$file}");

            return self::FAILURE;
        }

        if ($importer->hasPreviousImport() && ! $this->option('force')) {
            if ($this->option('dry-run')) {
                $this->warn('يوجد استيراد سابق — التقرير للعرض فقط، الاستيراد الفعلي يتطلب --force.');
            } else {
                $this->error('يوجد استيراد سابق. استخدم --force لحذفه وإعادة الاستيراد.');

                return self::FAILURE;
            }
        }

        try {
            $analysis = $importer->analyze($file, $this->option('sheet') ?: null);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['البند', 'القيمة'],
            [
                ['شركات في الكشف', count($analysis['companies'])],
                ['مطابَقة مع قاعدة البيانات', count($analysis['matched'])],
                ['غير مطابَقة', count($analysis['unmatched'])],
                ['إجمالي ذمم الكشف كاملاً (دينار)', number_format($analysis['total_sheet_jod'], 2)],
                ['إجمالي الذمم المستوردة (دينار)', number_format($analysis['total_matched_jod'], 2)],
            ],
        );

        if ($analysis['unmatched']) {
            $this->warn('شركات غير مطابَقة (تُراجع يدوياً):');
            $this->table(
                ['م', 'رقم العضوية', 'اسم الشركة'],
                collect($analysis['unmatched'])->map(fn ($c) => [$c['seq'], $c['membership_number'], $c['name']])->all(),
            );
        }

        $importer->logReport($analysis, (bool) $this->option('dry-run'));

        if ($this->option('dry-run')) {
            $this->info('وضع dry-run — لم يُكتب أي شيء.');

            return self::SUCCESS;
        }

        $result = $importer->import(
            $analysis['matched'],
            $analysis['unmatched'],
            (bool) $this->option('force'),
            null,
            (bool) $this->option('create-missing'),
        );
        $this->info("تم استيراد {$result['dues']} ذمة"
            . ($result['contractors_created'] > 0 ? " وإنشاء {$result['contractors_created']} شركة جديدة" : '')
            . '.');

        return self::SUCCESS;
    }
}
