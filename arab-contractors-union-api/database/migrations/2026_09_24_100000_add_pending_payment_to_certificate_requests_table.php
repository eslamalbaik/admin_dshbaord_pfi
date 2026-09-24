<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-17 #5 — ربط طلب شهادة العضوية بدفعة رسوم قيد التأكيد.
 *
 * المقاول يدفع الرسوم من التطبيق فتُسجَّل Payment بحالة pending، ولا يتحرّك paid_jod على
 * الذمم حتى يعتمدها المحاسب — فكان طلب الشهادة يُرفَض بـ403 (dues_below_threshold) ولا
 * يُنشأ صف إطلاقاً، فتظهر اللوحة فارغة للمقاول الذي دفع فعلاً.
 *
 * عمود إضافي لا توسيع لـ enum الحالة: الحالة pending كما هي، فكل مستهلك قائم
 * (CertificateRequestResource، فلاتر اللوحة، تطبيق المقاول) يبقى عاملاً بلا تغيير.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_requests', function (Blueprint $table) {
            $table->foreignId('pending_payment_id')
                ->nullable()
                ->after('status')
                ->constrained('payments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('certificate_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pending_payment_id');
        });
    }
};
