<?php

namespace Database\Seeders;

use App\Models\BankAccount;
use App\Models\Contractor;
use App\Models\News;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\SupportTicket;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * بيانات تجريبية للميزات الجديدة (الدفع/البنوك، الدعم الفني، الإعدادات،
 * الأخبار بوسائط، العطاءات). آمن للتشغيل على أي بيئة — idempotent:
 *
 *   php artisan db:seed --class=AppFeaturesDemoSeeder
 */
class AppFeaturesDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->settings();
        $this->bankAccounts();
        $this->news();
        $this->tenders();
        $this->supportAndPayments();

        $this->command->info('✔ تم إدخال البيانات التجريبية للميزات الجديدة.');
    }

    /** إعدادات التواصل — شاشة الدعم (واتساب/بريد/هاتف) */
    private function settings(): void
    {
        Setting::set('support_whatsapp', '+970599123456', 'contact');
        Setting::set('support_email', 'support@acu.ps', 'contact');
        Setting::set('support_phone', '+970822640000', 'contact');
    }

    /** الحسابات البنكية للاتحاد — شاشة الدفع */
    private function bankAccounts(): void
    {
        $banks = [
            [
                'bank_name'      => 'بنك فلسطين',
                'bank_name_en'   => 'Bank of Palestine',
                'iban'           => 'PS92PALS000000000400123456700',
                'account_number' => '400123456700',
                'account_holder' => 'اتحاد المقاولين العرب',
                'swift'          => 'PALSPS22',
                'is_active'      => true,
                'sort'           => 1,
            ],
            [
                'bank_name'      => 'البنك الإسلامي الفلسطيني',
                'bank_name_en'   => 'Palestine Islamic Bank',
                'iban'           => 'PS45PIBC000000000900987654300',
                'account_number' => '900987654300',
                'account_holder' => 'اتحاد المقاولين العرب',
                'swift'          => 'PIBCPS22',
                'is_active'      => true,
                'sort'           => 2,
            ],
        ];

        foreach ($banks as $b) {
            BankAccount::firstOrCreate(['iban' => $b['iban']], $b);
        }
    }

    /** أخبار الاتحاد — مع فيديو يوتيوب ورابط خارجي */
    private function news(): void
    {
        $author = User::where('role', 'admin')->value('id');

        $items = [
            [
                'title'        => 'افتتاح المقر الجديد لاتحاد المقاولين العرب',
                'excerpt'      => 'افتتح الاتحاد مقره الجديد بحضور نخبة من المقاولين وممثلي القطاع.',
                'body'         => '<p>احتفل اتحاد المقاولين العرب بافتتاح مقره الجديد ضمن خطته للتوسّع وتقديم خدمات أفضل للأعضاء.</p>',
                'video_url'    => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'external_url' => 'https://acu.ps',
                'category'     => 'news',
            ],
            [
                'title'        => 'توقيع مذكرة تفاهم لتأهيل الكوادر الهندسية',
                'excerpt'      => 'مذكرة تفاهم مع جهات أكاديمية لتدريب وتأهيل المهندسين الأعضاء.',
                'body'         => '<p>وقّع الاتحاد مذكرة تفاهم تهدف إلى رفع كفاءة الكوادر الهندسية عبر برامج تدريبية متخصصة.</p>',
                'external_url' => 'https://acu.ps/mou',
                'category'     => 'announcement',
            ],
            [
                'title'        => 'ملتقى المقاولين السنوي 2026',
                'excerpt'      => 'دعوة لحضور الملتقى السنوي لمناقشة تحديات القطاع وفرصه.',
                'body'         => '<p>يسر الاتحاد دعوتكم لحضور الملتقى السنوي الذي يجمع المقاولين وصنّاع القرار.</p>',
                'category'     => 'event',
            ],
        ];

        foreach ($items as $n) {
            News::firstOrCreate(
                ['slug' => News::generateSlug($n['title'])],
                array_merge($n, [
                    'is_published' => true,
                    'published_at' => now(),
                    'created_by'   => $author,
                ]),
            );
        }
    }

    /** عطاءات تجريبية (فقط إن كانت قليلة) */
    private function tenders(): void
    {
        if (Tender::count() >= 3) {
            return;
        }

        $by = User::where('role', 'admin')->value('id');

        $rows = [
            ['title' => 'عطاء توريد مواد بناء', 'category' => 'توريدات', 'budget' => 150000, 'status' => 'open',
             'description' => 'توريد إسمنت وحديد لمشروع الاتحاد.', 'submission_types' => ['email', 'file'], 'submission_email' => 'tenders@acu.ps', 'deadline' => now()->addDays(20)->toDateString()],
            ['title' => 'عطاء أعمال صيانة طرق', 'category' => 'إنشاءات', 'budget' => 320000, 'status' => 'open',
             'description' => 'صيانة وتعبيد طرق داخلية.', 'submission_types' => ['phone'], 'submission_phone' => '0599123456', 'deadline' => now()->addDays(30)->toDateString()],
            ['title' => 'عطاء تصميم مبنى إداري', 'category' => 'استشارات', 'budget' => 90000, 'status' => 'open',
             'description' => 'تصميم معماري وإنشائي لمبنى إداري.', 'submission_types' => ['email', 'phone', 'file'], 'submission_email' => 'design@acu.ps', 'submission_phone' => '0598000000', 'deadline' => now()->addDays(15)->toDateString()],
            ['title' => 'عطاء توريد أثاث مكتبي', 'category' => 'توريدات', 'budget' => 45000, 'status' => 'closed',
             'description' => 'أثاث مكتبي لمقر الاتحاد.', 'submission_types' => ['email'], 'submission_email' => 'tenders@acu.ps', 'deadline' => now()->addDays(10)->toDateString()],
        ];

        foreach ($rows as $r) {
            $r['created_by'] = $by;
            Tender::firstOrCreate(['title' => $r['title']], $r);
        }
    }

    /** تذاكر دعم ومعاملة تحويل بانتظار التأكيد — تحتاج مقاولاً موجوداً */
    private function supportAndPayments(): void
    {
        $contractor = Contractor::query()->orderBy('id')->first();

        if (! $contractor) {
            $this->command->warn('… لا يوجد مقاول — تم تخطي تذاكر الدعم والمدفوعات.');
            return;
        }

        SupportTicket::firstOrCreate(
            ['contractor_id' => $contractor->id, 'subject' => 'استفسار عن تجديد العضوية'],
            ['category' => 'inquiry', 'message' => 'أرغب بمعرفة خطوات تجديد العضوية والرسوم المطلوبة.', 'status' => 'open'],
        );

        SupportTicket::firstOrCreate(
            ['contractor_id' => $contractor->id, 'subject' => 'مشكلة في فتح شاشة الدفع'],
            [
                'category'   => 'technical',
                'message'    => 'لا تظهر لي بيانات البنوك عند فتح شاشة الدفع.',
                'status'     => 'answered',
                'reply'      => 'تم حل المشكلة، يرجى تحديث التطبيق لآخر إصدار.',
                'replied_by' => User::where('role', 'admin')->value('id'),
                'replied_at' => now(),
            ],
        );

        // معاملة تحويل بانتظار تأكيد المحاسبة
        Payment::firstOrCreate(
            ['contractor_id' => $contractor->id, 'reference_number' => 'DEMO-TRANSFER-001'],
            [
                'amount'       => 500,
                'type'         => 'membership_fee',
                'status'       => 'pending',
                'method'       => 'bank_transfer',
                'submitted_at' => now(),
                'notes'        => 'تحويل تجريبي بانتظار التأكيد.',
            ],
        );
    }
}
