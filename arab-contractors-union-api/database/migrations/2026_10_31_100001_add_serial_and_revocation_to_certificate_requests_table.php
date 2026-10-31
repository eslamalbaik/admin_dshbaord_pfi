<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * الرقم التسلسلي الفريد للشهادة + بيانات الإلغاء.
 *
 * serial_number: nullable (الطلبات غير الصادرة ما إلها رقم) لكن unique على مستوى قاعدة
 * البيانات، فما بتتكرر قيمة حتى لو تزامن إصداران. الشهادات الصادرة قبل هالميزة كانت
 * مطبوعة برقم MC-{id} (6 خانات) — بنحفظه كما هو عشان الشهادات الورقية المتداولة تضل قابلة للتحقق.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_requests', function (Blueprint $table) {
            $table->string('serial_number', 32)->nullable()->unique()->after('type');
            $table->timestamp('revoked_at')->nullable()->after('valid_until');
            $table->foreignId('revoked_by')->nullable()->after('revoked_at')->constrained('users')->nullOnDelete();
            $table->text('revoke_reason')->nullable()->after('revoked_by');
        });

        DB::table('certificate_requests')
            ->where('type', 'membership')
            ->where('status', 'issued')
            ->whereNull('serial_number')
            ->orderBy('id')
            ->get(['id'])
            ->each(fn ($row) => DB::table('certificate_requests')
                ->where('id', $row->id)
                ->update(['serial_number' => 'MC-' . str_pad((string) $row->id, 6, '0', STR_PAD_LEFT)]));
    }

    public function down(): void
    {
        Schema::table('certificate_requests', function (Blueprint $table) {
            $table->dropForeign(['revoked_by']);
            $table->dropUnique(['serial_number']);
            $table->dropColumn(['serial_number', 'revoked_at', 'revoked_by', 'revoke_reason']);
        });
    }
};
