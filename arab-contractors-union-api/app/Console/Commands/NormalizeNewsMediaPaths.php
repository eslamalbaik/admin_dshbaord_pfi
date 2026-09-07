<?php

namespace App\Console\Commands;

use App\Models\News;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * يحوّل قيم news.image/news.gallery القديمة (روابط كاملة مجمَّدة على APP_URL وقت الرفع)
 * إلى مسارات نسبية داخل قرص public — الموديل الآن يبني الرابط العام وقت القراءة (asset())
 * فيعكس الدومين الحالي دوماً بدل ما يبقى معلَّقاً على دومين قديم/خاطئ (سبب 404 على الإنتاج).
 */
class NormalizeNewsMediaPaths extends Command
{
    protected $signature = 'news:normalize-media-paths {--fix : كتابة المسارات النسبية فعلياً (بدونها العرض تقرير فقط dry-run)}';

    protected $description = 'تحويل news.image/news.gallery من روابط كاملة قديمة إلى مسارات نسبية';

    public function handle(): int
    {
        $fix = (bool) $this->option('fix');
        $rows = [];

        News::withTrashed()->chunkById(200, function ($items) use (&$rows, $fix) {
            foreach ($items as $news) {
                $imageRaw   = $news->getRawOriginal('image');
                $galleryRaw = $news->getRawOriginal('gallery');
                $galleryPaths = $galleryRaw ? (json_decode($galleryRaw, true) ?? []) : [];

                $changed = false;

                if ($imageRaw && str_contains($imageRaw, '/storage/')) {
                    $relative = Str::after(parse_url($imageRaw, PHP_URL_PATH) ?? '', '/storage/');
                    $rows[] = [$news->id, 'image', $imageRaw, $relative];
                    if ($fix) {
                        $news->image = $relative;
                        $changed = true;
                    }
                }

                $newGallery = [];
                $galleryChanged = false;
                foreach ($galleryPaths as $g) {
                    if (str_contains($g, '/storage/')) {
                        $relative = Str::after(parse_url($g, PHP_URL_PATH) ?? '', '/storage/');
                        $rows[] = [$news->id, 'gallery', $g, $relative];
                        $newGallery[] = $relative;
                        $galleryChanged = true;
                    } else {
                        $newGallery[] = $g;
                    }
                }

                if ($fix && $galleryChanged) {
                    $news->gallery = $newGallery;
                    $changed = true;
                }

                if ($fix && $changed) {
                    $news->save();
                }
            }
        });

        if ($rows === []) {
            $this->info('كل قيم image/gallery مسارات نسبية أصلاً — لا شيء للتحويل ✔');

            return self::SUCCESS;
        }

        $this->warn('عدد القيم المطلوب تحويلها: ' . count($rows) . ($fix ? ' (تم التحويل)' : ' (dry-run)'));
        $this->table(['ID', 'الحقل', 'القيمة القديمة', 'المسار الجديد'], $rows);

        if (! $fix)
            $this->line('هذا عرض فقط (dry-run). لكتابة المسارات فعلياً شغّل الأمر مع --fix');

        return self::SUCCESS;
    }
}
