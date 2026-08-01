<?php

namespace App\Console\Commands;

use App\Models\Contractor;
use App\Models\Membership;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateTestContractor extends Command
{
    protected $signature = 'contractor:create-test
                            {--password=Test@12345 : كلمة مرور اليوزر الاختباري}
                            {--name= : اسم الشركة (اختياري)}
                            {--phone= : رقم جوال محدّد (افتراضياً عشوائي 0599...)}
                            {--unactivated : إنشاء حساب غير مفعّل (بلا كلمة مرور/بلا تفعيل جوال) لاختبار تدفق التسجيل}';

    protected $description = 'إنشاء مقاول اختباري برقم عضوية جديد (هيكلية 928_g فما فوق) — جاهز للدخول، أو غير مفعّل لاختبار التسجيل عبر --unactivated';

    public function handle(): int
    {
        $membershipNumber = Contractor::nextMembershipNumber();
        $password         = $this->option('password');
        $unactivated      = (bool) $this->option('unactivated');
        $phone            = $this->option('phone') ?: '0599' . mt_rand(100000, 999999);

        // سجل تجاري عشوائي من 9 خانات غير مستخدم
        do {
            $commercialRegister = (string) mt_rand(100000000, 999999999);
        } while (Contractor::withTrashed()->where('commercial_register', $commercialRegister)->exists());

        $contractor = Contractor::create([
            'membership_number'   => $membershipNumber,
            'name'                => $this->option('name') ?: "شركة اختبار النظام {$membershipNumber}",
            'commercial_register' => $commercialRegister,
            'authorized_person'   => 'مستخدم اختباري',
            'owner_name'          => 'مستخدم اختباري',
            'trade'               => 'إنشاءات عامة',
            'classification'      => 'أ',
            'phone'               => $phone,
            'city'                => 'غزة',
            'status'              => 'active',
            'is_frozen'           => false,
            'profile_completed'   => ! $unactivated,
            // حساب غير مفعّل: بلا كلمة مرور وبلا تفعيل جوال → يمرّ بتدفق التسجيل (verify-identity)
            'phone_verified_at'   => $unactivated ? null : now(),
            'password'            => $unactivated ? null : Hash::make($password),
        ]);

        Membership::create([
            'contractor_id' => $contractor->id,
            'type'          => 'new',
            'status'        => 'active',
            'starts_at'     => now(),
            'expires_at'    => now()->addYear(),
            'amount'        => 0,
        ]);

        $this->info('تم إنشاء المقاول الاختباري بنجاح ✔');
        $this->table(
            ['الحقل', 'القيمة'],
            [
                ['رقم العضوية (membership_number)', $contractor->membership_number],
                ['رقم السجل التجاري (commercial_register)', $contractor->commercial_register],
                ['كلمة المرور (password)', $unactivated ? '— (غير مفعّل)' : $password],
                ['الاسم', $contractor->name],
                ['الجوال', $contractor->phone],
                ['الحالة', $unactivated ? 'غير مفعّل — جاهز لاختبار التسجيل' : 'مفعّل — جاهز للدخول'],
            ],
        );

        if ($unactivated) {
            $this->line("اختبر التسجيل: أدخل الجوال {$contractor->phone} في شاشة /contractor/register");
        } else {
            $this->line('جاهز لتسجيل الدخول مباشرة عبر POST /api/v1/contractor/auth/login');
        }

        return self::SUCCESS;
    }
}
