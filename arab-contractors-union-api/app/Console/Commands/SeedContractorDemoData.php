<?php

namespace App\Console\Commands;

use App\Models\Contractor;
use App\Models\Document;
use App\Models\Payment;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Console\Command;

class SeedContractorDemoData extends Command
{
    protected $signature = 'contractor:seed-demo-data
                            {membership_number=928_g : رقم عضوية المقاول المراد تعبئته ببيانات تجريبية}';

    protected $description = 'إضافة مدفوعات ومستندات وتذاكر دعم تجريبية لحساب مقاول موجود (لتجربة لوحة التحكم)';

    public function handle(): int
    {
        $membershipNumber = $this->argument('membership_number');

        $contractor = Contractor::where('membership_number', $membershipNumber)->first();
        if (! $contractor) {
            $this->error("لا يوجد مقاول برقم عضوية {$membershipNumber}");

            return self::FAILURE;
        }

        $membership = $contractor->memberships()->latest()->first();
        $adminUser  = User::first();

        // ── مدفوعات ─────────────────────────────────────────────────────────
        Payment::create([
            'contractor_id'  => $contractor->id,
            'membership_id'  => $membership?->id,
            'amount'         => 500,
            'type'           => 'membership_fee',
            'status'         => 'paid',
            'method'         => 'bank_transfer',
            'reference_number' => 'TRX-'.mt_rand(100000, 999999),
            'notes'          => 'دفعة رسوم العضوية السنوية',
            'paid_at'        => now()->subDays(20),
            'submitted_at'   => now()->subDays(21),
            'confirmed_by'   => $adminUser?->id,
            'confirmed_at'   => now()->subDays(20),
        ]);

        Payment::create([
            'contractor_id'  => $contractor->id,
            'membership_id'  => $membership?->id,
            'amount'         => 250,
            'type'           => 'other',
            'status'         => 'pending',
            'method'         => 'bank_transfer',
            'reference_number' => 'TRX-'.mt_rand(100000, 999999),
            'notes'          => 'إشعار تحويل بانتظار مراجعة المحاسبة',
            'submitted_at'   => now()->subDays(2),
        ]);

        Payment::create([
            'contractor_id'  => $contractor->id,
            'membership_id'  => $membership?->id,
            'amount'         => 150,
            'type'           => 'penalty',
            'status'         => 'rejected',
            'method'         => 'bank_transfer',
            'reference_number' => 'TRX-'.mt_rand(100000, 999999),
            'notes'          => 'مخالفة غرامة تأخير',
            'submitted_at'   => now()->subDays(10),
            'confirmed_by'   => $adminUser?->id,
            'confirmed_at'   => now()->subDays(9),
            'rejection_reason' => 'صورة الإشعار غير واضحة — يرجى إعادة الرفع',
        ]);

        // ── مستندات ─────────────────────────────────────────────────────────
        Document::create([
            'contractor_id' => $contractor->id,
            'title'         => 'رخصة مزاولة المهنة',
            'type'          => 'license',
            'url'           => 'demo/license-'.$contractor->id.'.pdf',
            'disk'          => 'local',
            'size'          => 245_000,
            'mime_type'     => 'application/pdf',
            'uploaded_by'   => $adminUser?->id,
        ]);

        Document::create([
            'contractor_id' => $contractor->id,
            'title'         => 'صورة الهوية / السجل التجاري',
            'type'          => 'id',
            'url'           => 'demo/id-'.$contractor->id.'.pdf',
            'disk'          => 'local',
            'size'          => 180_000,
            'mime_type'     => 'application/pdf',
            'uploaded_by'   => $adminUser?->id,
        ]);

        Document::create([
            'contractor_id' => $contractor->id,
            'title'         => 'شهادة تصنيف المقاول',
            'type'          => 'certificate',
            'url'           => 'demo/certificate-'.$contractor->id.'.pdf',
            'disk'          => 'local',
            'size'          => 320_000,
            'mime_type'     => 'application/pdf',
            'uploaded_by'   => $adminUser?->id,
        ]);

        // ── تذاكر دعم ────────────────────────────────────────────────────────
        SupportTicket::create([
            'contractor_id' => $contractor->id,
            'subject'       => 'استفسار عن تجديد العضوية',
            'category'      => 'inquiry',
            'message'       => 'متى يجب تجديد العضوية قبل انتهائها؟',
            'status'        => 'answered',
            'reply'         => 'يُفضّل تجديد العضوية قبل شهر من تاريخ الانتهاء عبر بوابة الأعضاء.',
            'replied_by'    => $adminUser?->id,
            'replied_at'    => now()->subDays(3),
        ]);

        SupportTicket::create([
            'contractor_id' => $contractor->id,
            'subject'       => 'مشكلة في رفع صورة إشعار الدفع',
            'category'      => 'technical',
            'message'       => 'التطبيق لا يقبل صورة إشعار التحويل عند رفعها.',
            'status'        => 'open',
        ]);

        $this->info("تمت إضافة بيانات تجريبية لحساب {$membershipNumber} بنجاح ✔");
        $this->table(
            ['العنصر', 'العدد المضاف'],
            [
                ['مدفوعات', 3],
                ['مستندات', 3],
                ['تذاكر دعم', 2],
            ],
        );

        return self::SUCCESS;
    }
}
