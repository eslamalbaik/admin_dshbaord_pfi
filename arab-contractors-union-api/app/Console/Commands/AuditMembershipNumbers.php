<?php

namespace App\Console\Commands;

use App\Models\Contractor;
use App\Rules\MembershipNumber;
use Illuminate\Console\Command;

class AuditMembershipNumbers extends Command
{
    protected $signature = 'contractor:audit-memberships
                            {--fix : إزالة أرقام العضوية المخالفة للهيكلية المعتمدة (تصبح NULL فلا يستطيع صاحبها الدخول)}';

    protected $description = 'تدقيق أرقام العضوية في قاعدة البيانات وفق الهيكلية المعتمدة (1-927 قديم أو 928_g فما فوق) وإصلاح المخالف منها';

    public function handle(): int
    {
        $invalid = Contractor::withTrashed()
            ->whereNotNull('membership_number')
            ->get(['id', 'membership_number', 'name'])
            ->filter(fn ($c) => ! MembershipNumber::isValid($c->membership_number));

        if ($invalid->isEmpty()) {
            $this->info('كل أرقام العضوية سليمة وفق الهيكلية المعتمدة ✔');

            return self::SUCCESS;
        }

        $this->warn("عدد الأرقام المخالفة للهيكلية: {$invalid->count()}");
        $this->table(
            ['ID', 'رقم العضوية المخالف', 'الاسم'],
            $invalid->map(fn ($c) => [$c->id, $c->membership_number, $c->name])->all(),
        );

        if (! $this->option('fix')) {
            $this->line('هذا عرض فقط (dry-run). لإزالة الأرقام المخالفة شغّل الأمر مع --fix');

            return self::SUCCESS;
        }

        Contractor::withTrashed()
            ->whereIn('id', $invalid->pluck('id'))
            ->update(['membership_number' => null]);

        $this->info("تمت إزالة {$invalid->count()} رقم عضوية مخالف. أصحابها لن يستطيعوا الدخول حتى يُمنحوا رقماً معتمداً.");

        return self::SUCCESS;
    }
}
