<?php

use App\Support\CriticalEvents;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * سجل المحددات الهامة: علَم على سجل النشاط يميّز الأحداث المخالفة للوضع
 * الطبيعي (تعديل رسوم العضوية، تعديل إصدار شهادة عضوية...) — انظر CriticalEvents.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->boolean('is_critical')->default(false)->after('action');
            $table->index(['is_critical', 'created_at']);
        });

        // الأحداث القديمة من الأنواع الحرجة دائماً تظهر بالسجل الخاص (بدون قيم قبل/بعد
        // لأنها ما كانت تُسجَّل). certificate.issued/admin_issued_membership ما بتنعلّم هون
        // لأنها حرجة بشرط (استبدال شهادة صادرة / بيانات مخالفة لسجل المقاول) ما بنقدر نتحقق منه بأثر رجعي.
        DB::table('activity_logs')
            ->whereIn('action', CriticalEvents::ALWAYS_CRITICAL)
            ->update(['is_critical' => true]);
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['is_critical', 'created_at']);
            $table->dropColumn('is_critical');
        });
    }
};
