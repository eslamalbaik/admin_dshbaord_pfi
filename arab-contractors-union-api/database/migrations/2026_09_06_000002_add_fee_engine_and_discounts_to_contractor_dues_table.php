<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * يضيف دعم محرّك احتساب الرسوم (source='fee_engine') ونظام الخصومات الفردي/الجماعي (المادة 37/ت)
 * على جدول contractor_dues.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contractor_dues', function (Blueprint $table) {
            $table->enum('discount_type', ['percent', 'fixed'])->nullable()->after('amount_jod');
            $table->decimal('discount_value', 10, 2)->nullable()->after('discount_type');
            // المبلغ المخصوم الفعلي بالدينار — يُحفظ محسوباً وقت التطبيق فلا يُعاد اشتقاقه لاحقاً من original_amount_jod
            $table->decimal('discount_amount_jod', 10, 2)->nullable()->after('discount_value');
            $table->string('discount_reason', 255)->nullable()->after('discount_amount_jod');
            $table->foreignId('discount_by')->nullable()->after('discount_reason')->constrained('users')->nullOnDelete();
            // المبلغ الأصلي قبل أي خصم — يُسجَّل مرة واحدة عند أول خصم يُطبَّق
            $table->decimal('original_amount_jod', 10, 2)->nullable()->after('discount_by');
            // لقطة تفصيل الاحتساب (لكل مجال: الدرجة المعتمدة والنسبة) — فقط لذمم source='fee_engine'
            $table->json('fee_breakdown')->nullable()->after('original_amount_jod');

            $table->index(['contractor_id', 'year', 'source']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE contractor_dues MODIFY COLUMN source ENUM('legacy_import','manual','fee_engine') NOT NULL DEFAULT 'manual'");
        }
    }

    public function down(): void
    {
        Schema::table('contractor_dues', function (Blueprint $table) {
            $table->dropIndex(['contractor_id', 'year', 'source']);
            $table->dropConstrainedForeignId('discount_by');
            $table->dropColumn([
                'discount_type', 'discount_value', 'discount_amount_jod',
                'discount_reason', 'original_amount_jod', 'fee_breakdown',
            ]);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE contractor_dues MODIFY COLUMN source ENUM('legacy_import','manual') NOT NULL DEFAULT 'manual'");
        }
    }
};
