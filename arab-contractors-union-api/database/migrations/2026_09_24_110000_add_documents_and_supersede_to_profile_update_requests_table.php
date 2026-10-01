<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-17 US11 — الطابور كان مبنياً لخمسة حقول نصية قصيرة، والمسار الذي يستعمله التطبيق
 * فعلاً يقبل ~25 حقلاً و16 مستنداً. هذه الإضافات تُتيح له استيعابهما.
 *
 * كلها إضافية: الصفوف القائمة تبقى صالحة، و mine()/index() لا يتأثران، والتراجع العملي يكون
 * بإطفاء مفتاح PROFILE_EDITS_REQUIRE_APPROVAL لا بإسقاط الأعمدة.
 *
 * توسيع الـenum يختلف بين المحرّكين — نفس نمط 2026_09_19_000003_expand_penalty_statuses:
 * MySQL/MariaDB يقبل ALTER MODIFY، أما SQLite (بالاختبارات) فيحاكي enum() بقيد CHECK لا
 * يُعدَّل بدون doctrine/dbal غير المثبَّت هنا، فيُعاد بناء الجدول يدوياً مع نقل البيانات.
 *
 * ملاحظة MariaDB: ALTER MODIFY يُعيد بناء الجدول. profile_update_requests صغير فالعملية
 * ثوانٍ، لكن يُفضَّل تنفيذها بنافذة هادئة على الإنتاج — ونشر staging يُشغّل migrate --force
 * تلقائياً عند الدفع.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profile_update_requests', function (Blueprint $table) {
            // خريطة: اسم حقل المستند → مسار الملف المرحَّل (staged) بانتظار الموافقة.
            // عمود attachment القائم هو "إثبات" المقاول لطلبه، لا حمولة الطلب نفسها.
            if (! Schema::hasColumn('profile_update_requests', 'proposed_files')) {
                $table->json('proposed_files')->nullable()->after('proposed_data');
            }
            if (! Schema::hasColumn('profile_update_requests', 'superseded_at')) {
                $table->dateTime('superseded_at')->nullable()->after('reviewed_at');
            }
        });

        // طلب جديد يُلغي الطلب المعلّق السابق بدل أن يُرفَض: مع الملف الكامل، منع التعديل
        // الثاني يعني أن مقاولاً أخطأ في حقل واحد ينتظر أياماً قبل أن يصحّحه.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE profile_update_requests MODIFY status ENUM('pending','approved','rejected','superseded') NOT NULL DEFAULT 'pending'");

            return;
        }

        $this->rebuildForSqlite(['pending', 'approved', 'rejected', 'superseded']);
    }

    public function down(): void
    {
        // قبل تضييق الـenum: أي صف بحالة superseded غير موجودة بالقديم كان سيُفشل الـALTER
        // أو يُقصّ قيمته بصمت لو نُفِّذ rollback على بيانات حقيقية تستخدمها.
        DB::table('profile_update_requests')->where('status', 'superseded')->update(['status' => 'rejected']);

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE profile_update_requests MODIFY status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'");
        } else {
            $this->rebuildForSqlite(['pending', 'approved', 'rejected']);
        }

        Schema::table('profile_update_requests', function (Blueprint $table) {
            $table->dropColumn(['proposed_files', 'superseded_at']);
        });
    }

    /** إعادة بناء الجدول بقيد CHECK جديد للـenum، مع نقل كل البيانات (SQLite فقط). */
    private function rebuildForSqlite(array $statuses): void
    {
        $columns = 'id, contractor_id, proposed_data, proposed_files, attachment, phone_otp_verified_at, '
            . 'status, reject_reason, reviewed_by, reviewed_at, superseded_at, created_at, updated_at';

        Schema::rename('profile_update_requests', 'pur_old_status_migration');

        Schema::create('profile_update_requests', function (Blueprint $table) use ($statuses) {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->json('proposed_data');
            $table->json('proposed_files')->nullable();
            $table->string('attachment')->nullable();
            $table->dateTime('phone_otp_verified_at')->nullable();
            $table->enum('status', $statuses)->default('pending');
            $table->string('reject_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->dateTime('superseded_at')->nullable();
            $table->timestamps();
        });

        DB::statement("INSERT INTO profile_update_requests ({$columns}) SELECT {$columns} FROM pur_old_status_migration");

        Schema::drop('pur_old_status_migration');
    }
};
