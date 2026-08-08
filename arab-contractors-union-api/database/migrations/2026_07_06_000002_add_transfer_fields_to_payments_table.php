<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * حقول التحويل البنكي على جدول المدفوعات:
 * يرفع المقاول إشعار التحويل (صورة + مبلغ) فتُسجَّل معاملة بحالة "pending"
 * ثم يقوم موظف المحاسبة بالتأكيد (paid) أو الرفض (rejected).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('bank_account_id')->nullable()->after('membership_id')
                  ->constrained('bank_accounts')->nullOnDelete();
            $table->string('receipt_image')->nullable()->after('reference_number'); // صورة إشعار الدفع
            $table->timestamp('submitted_at')->nullable()->after('paid_at');        // وقت رفع الإشعار
            $table->foreignId('confirmed_by')->nullable()->after('submitted_at')
                  ->constrained('users')->nullOnDelete();                            // المحاسب المؤكِّد
            $table->timestamp('confirmed_at')->nullable()->after('confirmed_by');
            $table->text('rejection_reason')->nullable()->after('confirmed_at');
        });

        // إضافة حالة "rejected" إلى enum الحالة — MySQL فقط (sqlite بيئة الاختبار لا تدعم MODIFY COLUMN)
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM('pending','paid','refunded','failed','rejected') NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_account_id');
            $table->dropConstrainedForeignId('confirmed_by');
            $table->dropColumn(['receipt_image', 'submitted_at', 'confirmed_at', 'rejection_reason']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM('pending','paid','refunded','failed') NOT NULL DEFAULT 'pending'");
        }
    }
};
