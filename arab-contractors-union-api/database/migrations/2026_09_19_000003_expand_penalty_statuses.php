<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * توسيع حالات الغرامة من (unpaid/paid) إلى أربع حالات معتمَدة (REQ-06 #6 بالشيت):
 * unpaid / paid / partially_paid / rejected.
 *
 * الأعمدة الجديدة (paid_amount, reject_reason) تُضاف بـ Schema::table العادي على كلا
 * المحرّكين. لكن توسيع enum نفسه يختلف: MySQL يقبل ALTER MODIFY مباشرة، بينما SQLite
 * (مستخدَم فقط بالاختبارات المحلية) يحاكي enum() بقيد CHECK لا يمكن تعديله بدون
 * doctrine/dbal (غير مثبَّت بالمشروع) — فنعيد بناء الجدول يدوياً على SQLite فقط.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penalties', function (Blueprint $table) {
            if (!Schema::hasColumn('penalties', 'paid_amount'))
                $table->decimal('paid_amount', 10, 2)->default(0)->after('amount');
            if (!Schema::hasColumn('penalties', 'reject_reason'))
                $table->string('reject_reason')->nullable()->after('notes');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE penalties MODIFY status ENUM('unpaid','paid','partially_paid','rejected') DEFAULT 'unpaid'");

            return;
        }

        // SQLite: أعِد بناء الجدول بقيد CHECK جديد يشمل الحالتين الإضافيتين، مع نقل البيانات كاملة.
        Schema::rename('penalties', 'penalties_old_status_migration');

        Schema::create('penalties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->string('reason');
            $table->decimal('amount', 10, 2)->default(0);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->enum('status', ['unpaid', 'paid', 'partially_paid', 'rejected'])->default('unpaid');
            $table->text('notes')->nullable();
            $table->string('reject_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        DB::statement('
            INSERT INTO penalties (id, contractor_id, reason, amount, paid_amount, status, notes, reject_reason, paid_at, created_at, updated_at)
            SELECT id, contractor_id, reason, amount, paid_amount, status, notes, reject_reason, paid_at, created_at, updated_at
            FROM penalties_old_status_migration
        ');

        Schema::drop('penalties_old_status_migration');
    }

    public function down(): void
    {
        // لازم قبل تضييق الـ enum: أي صف بحالة 'partially_paid'/'rejected' غير موجودة
        // بالـ enum القديم كان يتسبب بفشل الـ ALTER (أو truncation صامت لقيمة الحالة)
        // لو نُفِّذ rollback على بيانات حقيقية تستخدم الحالتين الجديدتين فعلياً.
        DB::table('penalties')->whereIn('status', ['partially_paid', 'rejected'])->update(['status' => 'unpaid']);

        Schema::table('penalties', function (Blueprint $table) {
            $table->dropColumn(['paid_amount', 'reject_reason']);
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE penalties MODIFY status ENUM('unpaid','paid') DEFAULT 'unpaid'");
        }
    }
};
