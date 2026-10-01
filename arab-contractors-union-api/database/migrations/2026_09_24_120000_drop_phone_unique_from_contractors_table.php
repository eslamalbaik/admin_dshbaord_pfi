<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * إزالة قيد UNIQUE عن contractors.phone.
 *
 * السبب: المالك الواحد قد يملك عدة شركات ويستعمل نفس رقم الجوال لها جميعاً — وهذا
 * وضع مشروع في سجلات الاتحاد (20 شركة من كشف 2026 المسدّد تقع في 10 مجموعات كهذه).
 * القيد كان يمنع إدخالها أصلاً.
 *
 * ⚠️ تحذير مرتبط: ContractorRegisterController ما زال يبحث بـ
 * `Contractor::where('phone', ...)->first()` في مداخل المصادقة الستة
 * (verifyIdentity / verifyOtp / resendOtp / setPassword / forgotPasswordSendOtp /
 * forgotPasswordReset). بعد رفع هذا القيد تصبح `->first()` غامضة: أول شركة بالـ id
 * تفوز، والباقي لا يستطيع التسجيل ولا استعادة كلمة المرور — بصمت.
 * الإصلاح المتفق عليه: تحويل تلك المداخل للبحث بـ membership_number + التحقق من
 * تطابق الهاتف. لا تعتبر هذه الهجرة مكتملة قبل تنفيذه.
 */
return new class extends Migration
{
    private const INDEX = 'contractors_phone_unique';

    public function up(): void
    {
        if (! $this->indexExists(self::INDEX)) {
            return; // مطبَّقة مسبقاً — الهجرة idempotent لأنها طُبّقت يدوياً على production قبل نشر الكود
        }

        Schema::table('contractors', function ($table) {
            $table->dropUnique(self::INDEX);
        });

        // فهرس عادي بديل: البحث بالهاتف ما زال مستخدماً وبحاجة لفهرس، لكن بلا تفرّد
        if (! $this->indexExists('contractors_phone_index')) {
            Schema::table('contractors', function ($table) {
                $table->index('phone', 'contractors_phone_index');
            });
        }
    }

    public function down(): void
    {
        // لا يمكن إعادة القيد ما دامت هناك أرقام مكررة فعلياً — نحذف الفهرس العادي فقط.
        if ($this->indexExists('contractors_phone_index')) {
            Schema::table('contractors', function ($table) {
                $table->dropIndex('contractors_phone_index');
            });
        }

        $duplicates = DB::table('contractors')
            ->selectRaw('phone, COUNT(*) AS c')
            ->whereNotNull('phone')
            ->groupBy('phone')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($duplicates > 0) {
            throw new RuntimeException(
                "لا يمكن إعادة قيد UNIQUE على contractors.phone: يوجد {$duplicates} رقم مكرر. "
                .'وحّد الأرقام أولاً ثم أعد المحاولة.'
            );
        }

        Schema::table('contractors', function ($table) {
            $table->unique('phone', self::INDEX);
        });
    }

    /**
     * information_schema موجود على MySQL/MariaDB فقط — على SQLite (الاختبارات) يرمي
     * "no such table: information_schema.statistics" فيُسقط كامل مجموعة الاختبارات.
     * نفس نمط DB::getDriverName() المستعمل في مهاجرات أخرى بهذا المجلد.
     */
    private function indexExists(string $name): bool
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            foreach (DB::select("PRAGMA index_list('contractors')") as $index) {
                if (($index->name ?? null) === $name) {
                    return true;
                }
            }

            return false;
        }

        return DB::selectOne(
            'SELECT 1 FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?
             LIMIT 1',
            ['contractors', $name]
        ) !== null;
    }
};
