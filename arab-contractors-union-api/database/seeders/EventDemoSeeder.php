<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * فعاليات تجريبية لواجهة الفعاليات في الموقع والتطبيق.
 * التشغيل: php artisan db:seed --class=EventDemoSeeder
 */
class EventDemoSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = User::first()?->id;

        Event::updateOrCreate(
            ['slug' => 'fidic-training-workshop'],
            [
                'title'          => 'ورشة تدريبية متخصصة في عقود الفيديك FIDIC',
                'excerpt'        => 'ينظم مركز التدريب في الاتحاد ورشة متخصصة في عقود الفيديك بواقع أربعة أيام تدريبية لمنتسبي الشركات الأعضاء.',
                'body'           => "ينظم مركز التدريب وبناء القدرات في اتحاد المقاولين الفلسطينيين ورشة تدريبية متخصصة في عقود الفيديك (FIDIC) خلال الشهر القادم.\n\nتستهدف الورشة مدراء المشاريع والمهندسين العاملين في شركات المقاولات الأعضاء، وتغطي: مقدمة في عقود الفيديك، الكتاب الأحمر والأصفر، إدارة المطالبات والتغييرات، وتسوية النزاعات التعاقدية.\n\nمدة الورشة أربعة أيام تدريبية، والمقاعد محدودة — للتسجيل يرجى التواصل مع مركز التدريب.",
                'event_date'     => now()->addDays(20),
                'event_location' => 'مقر الاتحاد الرئيسي — رام الله',
                'event_format'   => 'onsite',
                'is_published'   => true,
                'published_at'   => now()->subDays(8),
                'created_by'     => $adminId,
            ],
        );

        $this->command?->info('تمت إضافة فعالية تجريبية بنجاح ✔');
    }
}
