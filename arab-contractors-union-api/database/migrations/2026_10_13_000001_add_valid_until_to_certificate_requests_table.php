<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * تاريخ انتهاء شهادة العضوية ("سارية المفعول حتى") — بينحفظ على الطلب لحظة توليد
 * الشهادة، فالتطبيق بيعرض نفس التاريخ المطبوع حتى لو العضوية تجدّدت بعدين.
 *
 * الشهادات الصادرة قبل هالحقل: بنعبّيها من عضوية المقاول اللي كانت سارية يوم الإصدار.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_requests', function (Blueprint $table) {
            $table->date('valid_until')->nullable()->after('decision_date');
        });

        DB::table('certificate_requests')
            ->where('type', 'membership')
            ->where('status', 'issued')
            ->whereNull('valid_until')
            ->whereNotNull('issued_at')
            ->orderBy('id')
            ->get(['id', 'contractor_id', 'issued_at'])
            ->each(function ($row) {
                $issuedOn = substr((string) $row->issued_at, 0, 10);

                $expiresAt = DB::table('memberships')
                    ->where('contractor_id', $row->contractor_id)
                    ->where('status', 'active')
                    ->whereNotNull('expires_at')
                    ->whereDate('expires_at', '>=', $issuedOn)
                    ->where(fn ($q) => $q->whereNull('starts_at')->orWhereDate('starts_at', '<=', $issuedOn))
                    ->orderBy('expires_at')
                    ->value('expires_at');

                if ($expiresAt) {
                    DB::table('certificate_requests')
                        ->where('id', $row->id)
                        ->update(['valid_until' => substr((string) $expiresAt, 0, 10)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('certificate_requests', function (Blueprint $table) {
            $table->dropColumn('valid_until');
        });
    }
};
