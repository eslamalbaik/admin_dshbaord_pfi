<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * قاعدة "الدفع المسبق" القديمة بـ MembershipRenewalService (PR #25، من 2026-10-05) كانت تمدّ
 * العضوية لـ 31/12 السنة الجاية لو في عضوية فعّالة بتغطي سنة الدفع، فطلع حساب 9541_g بـ 2027-12-31.
 * بنرجّع بس العضويات اللي كتبتها هالقاعدة: بدايتها 1/1 من سنة بعد سنة الموافقة، وانوافق عليها
 * من 2026-10-05 ولقدّام ← بتنتهي 31/12 من سنة الموافقة وبتبدأ يوم الموافقة.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('memberships')
            ->where('status', 'active')
            ->whereNotNull('reviewed_at')
            ->where('reviewed_at', '>=', '2026-10-05 00:00:00')
            ->whereNotNull('starts_at')
            ->whereNotNull('expires_at')
            ->orderBy('id')
            ->get(['id', 'starts_at', 'expires_at', 'reviewed_at'])
            ->each(function ($m) {
                $reviewed = Carbon::parse($m->reviewed_at);
                $starts   = Carbon::parse($m->starts_at);
                $expires  = Carbon::parse($m->expires_at);

                $isPrepaid = $expires->year > $reviewed->year
                    && $starts->format('m-d') === '01-01'
                    && $starts->year === $expires->year;

                if (! $isPrepaid) {
                    return;
                }

                DB::table('memberships')->where('id', $m->id)->update([
                    'starts_at'  => $reviewed->toDateString(),
                    'expires_at' => Carbon::create($reviewed->year, 12, 31)->toDateString(),
                ]);
            });
    }

    public function down(): void
    {
        // تصحيح بيانات — ما في رجوع
    }
};
