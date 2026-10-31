<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * مرة وحدة: المقاولين المحفوظين "منتهي" واللي الأرصدة بتعرضهم "فعّالة" (سدّدوا ذمم السنة)
 * بتصير حالتهم active وبتنعملّهم عضوية لحد 31/12 من السنة — نفس MembershipBalanceObserver
 * للحالات اللي صارت قبل ما ينضاف.
 */
return new class extends Migration
{
    public function up(): void
    {
        $service = app(\App\Services\MembershipRenewalService::class);

        DB::table('contractors')->whereNull('deleted_at')->where('status', 'expired')
            ->orderBy('id')->pluck('id')
            ->each(fn ($id) => $service->syncWithBalance((int) $id));
    }

    public function down(): void
    {
        // العضويات المنشأة معلَّمة بـ MembershipRenewalService::balanceMarker() لو لزم الرجوع يدوياً
    }
};
