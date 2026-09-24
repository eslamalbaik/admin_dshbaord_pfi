<?php

namespace Database\Seeders;

use App\Models\Contractor;
use App\Models\Event;
use App\Models\News;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * بيانات تجريبية بكميات كبيرة (40 من كل نوع) — لتعبئة الموقع/التطبيق بمحتوى كافٍ للعرض.
 * كل السجلات معلّمة بوضوح كـ"تجريبية" (بريد/رقم عضوية/مرجع بنطاق مخصص لا يتقاطع مع بيانات حقيقية)
 * وقابلة للحذف بسهولة لاحقاً عبر البحث عن البادئة DEMO / نطاق membership_number.
 *
 * التشغيل: php artisan db:seed --class=Bulk40DemoSeeder
 * الحذف لاحقاً: احذف السجلات اللي license_number/membership_number يبدأ بـ DEMO أو ضمن مدى 9500_g-9539_g،
 * وtenders/news/events اللي reference_number/slug يبدأ بـ demo-.
 */
class Bulk40DemoSeeder extends Seeder
{
    private const COUNT = 40;

    public function run(): void
    {
        $admin = User::where('role', 'admin')->first() ?? User::first();

        $contractors = $this->seedContractors();
        $this->seedTenders($admin?->id);
        $this->seedNews($admin?->id);
        $this->seedEvents($admin?->id);

        $this->command?->info("✅ تمت إضافة {$contractors->count()} مقاول + " . self::COUNT . ' عطاء + ' . self::COUNT . ' خبر + ' . self::COUNT . ' فعالية (بيانات تجريبية).');
    }

    private function seedContractors()
    {
        $companyWords  = ['المدار', 'النور', 'الأمل', 'البناء الحديث', 'الفارابي', 'التقنية', 'الرواد', 'الإعمار', 'المستقبل', 'الوفاء'];
        $companyKinds  = ['شركة', 'مؤسسة', 'مقاولات'];
        $cities        = ['غزة', 'رفح', 'خانيونس', 'الوسطى', 'شمال غزة', 'رام الله', 'نابلس', 'جنين', 'بيت لحم', 'الخليل'];
        $classes       = ['أ', 'ب', 'ج', 'د'];
        $statuses      = ['active', 'active', 'active', 'pending', 'expired'];

        $contractors = collect();

        for ($i = 1; $i <= self::COUNT; $i++) {
            $seq       = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $kind      = $companyKinds[$i % count($companyKinds)];
            $word      = $companyWords[$i % count($companyWords)];

            $contractor = Contractor::updateOrCreate(
                ['license_number' => "DEMO-LIC-{$seq}"],
                [
                    'membership_number' => (9500 + $i) . '_g',
                    'name'              => "{$kind} {$word} للمقاولات ({$seq})",
                    'authorized_person' => 'المفوّض بالتوقيع ' . $seq,
                    'commercial_register' => "DEMO-CR-{$seq}",
                    'classification'    => $classes[$i % count($classes)],
                    'established_year'  => rand(1995, 2020),
                    'owner_name'        => "صاحب {$kind} {$word}",
                    'email'             => "demo-contractor-{$seq}@example-demo.union.ps",
                    'phone'             => '0599-9' . str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                    'phone_verified_at' => now(),
                    'city'              => $cities[$i % count($cities)],
                    'address'           => 'عنوان تجريبي رقم ' . $seq,
                    'status'            => $statuses[$i % count($statuses)],
                    'profile_completed' => true,
                    'password'          => Hash::make('Demo@12345'),
                ],
            );

            $contractors->push($contractor);
        }

        return $contractors;
    }

    private function seedTenders(?int $adminId): void
    {
        $actions    = ['طرح عطاء إنشاء', 'طرح عطاء توسعة', 'طرح عطاء ترميم', 'طرح عطاء تمديد شبكة', 'طرح عطاء صيانة'];
        $objects    = ['مبنى إداري', 'شبكة طرق', 'محطة مياه', 'مدرسة حكومية', 'مركز صحي', 'ملعب رياضي'];
        $locations  = ['غزة', 'رفح', 'خانيونس', 'رام الله', 'نابلس', 'جنين', 'بيت لحم', 'الخليل'];
        $categories = Tender::CATEGORIES;
        $statuses   = ['open', 'open', 'open', 'closed', 'cancelled'];
        $submissionSets = [['email'], ['file'], ['email', 'file'], ['phone'], ['email', 'phone', 'file']];

        for ($i = 1; $i <= self::COUNT; $i++) {
            $seq    = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $action = $actions[$i % count($actions)];
            $object = $objects[$i % count($objects)];
            $city   = $locations[$i % count($locations)];
            $status = $statuses[$i % count($statuses)];
            $submissionTypes = $submissionSets[$i % count($submissionSets)];

            Tender::updateOrCreate(
                ['reference_number' => "DEMO-TND-{$seq}"],
                [
                    'title'            => "{$action} {$object} — {$city} ({$seq})",
                    'issuing_entity'   => 'اتحاد المقاولين الفلسطينيين',
                    'description'      => "تفاصيل تجريبية لعطاء {$object} في محافظة {$city}. رقم مرجعي DEMO-TND-{$seq}.",
                    'union_notes'      => 'بيانات تجريبية لأغراض العرض فقط.',
                    'category'         => $categories[$i % count($categories)],
                    'budget'           => rand(100, 2000) * 1000,
                    'deadline'         => now()->addDays(rand(10, 90)),
                    'published_at'     => now()->subDays(rand(0, 30)),
                    'status'           => $status,
                    'submission_types' => $submissionTypes,
                    'submission_email' => in_array('email', $submissionTypes, true) ? "tenders-demo-{$seq}@example-demo.union.ps" : null,
                    'submission_phone' => in_array('phone', $submissionTypes, true) ? '0599-8' . str_pad((string) $i, 6, '0', STR_PAD_LEFT) : null,
                    'created_by'       => $adminId,
                ],
            );
        }
    }

    private function seedNews(?int $adminId): void
    {
        $titles = [
            'انطلاق أعمال المؤتمر السنوي لاتحاد المقاولين',
            'إعلان بخصوص تجديد العضوية إلكترونياً',
            'توقيع مذكرة تفاهم مع جهة شريكة',
            'تنويه بخصوص تحديث بيانات الشركات الأعضاء',
            'ورشة عمل حول معايير السلامة المهنية',
            'اجتماع دوري للجنة التصنيف المهني',
            'إطلاق خدمة إلكترونية جديدة في البوابة',
            'زيارة ميدانية لمشاريع أعضاء الاتحاد',
        ];
        $categories = ['news', 'announcement', 'news', 'tender'];

        for ($i = 1; $i <= self::COUNT; $i++) {
            $seq   = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $title = $titles[$i % count($titles)] . " ({$seq})";

            News::updateOrCreate(
                ['slug' => "demo-news-{$seq}"],
                [
                    'title'        => $title,
                    'excerpt'      => "ملخص تجريبي للخبر رقم {$seq} — لأغراض العرض فقط.",
                    'body'         => "نص تجريبي كامل للخبر رقم {$seq}.\n\nهذا المحتوى مُولَّد تلقائياً لتعبئة واجهة الأخبار بعدد كافٍ من العناصر لغايات العرض والاختبار.",
                    'category'     => $categories[$i % count($categories)],
                    'is_published' => true,
                    'published_at' => now()->subDays(rand(0, 60)),
                    'created_by'   => $adminId,
                ],
            );
        }
    }

    private function seedEvents(?int $adminId): void
    {
        $titles = [
            'ورشة تدريبية في عقود الفيديك FIDIC',
            'ندوة حول تحديثات كود البناء',
            'لقاء تعريفي بخدمات الاتحاد الإلكترونية',
            'مؤتمر قطاع الإنشاءات السنوي',
            'دورة تدريبية في إدارة المشاريع',
            'يوم مفتوح لأعضاء الاتحاد',
        ];
        $locations = ['مقر الاتحاد — رام الله', 'فرع الاتحاد — غزة', 'فرع الاتحاد — نابلس', 'قاعة مؤتمرات خارجية'];
        $formats   = ['onsite', 'online', 'hybrid'];

        for ($i = 1; $i <= self::COUNT; $i++) {
            $seq   = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $title = $titles[$i % count($titles)] . " ({$seq})";

            Event::updateOrCreate(
                ['slug' => "demo-event-{$seq}"],
                [
                    'title'          => $title,
                    'excerpt'        => "ملخص تجريبي للفعالية رقم {$seq}.",
                    'body'           => "تفاصيل تجريبية كاملة للفعالية رقم {$seq} — لأغراض العرض والاختبار فقط.",
                    'event_date'     => now()->addDays(rand(1, 60)),
                    'event_location' => $locations[$i % count($locations)],
                    'event_format'   => $formats[$i % count($formats)],
                    'is_published'   => true,
                    'published_at'   => now()->subDays(rand(0, 20)),
                    'created_by'     => $adminId,
                ],
            );
        }
    }
}
