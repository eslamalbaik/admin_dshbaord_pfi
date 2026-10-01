<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * توسيع deadline من DATE إلى DATETIME (REQ-07 #2) — يلزم وقت التقديم النهائي، لا التاريخ فقط.
 *
 * MySQL: ALTER MODIFY مباشر. SQLite (اختبارات محلية فقط) مكتوبة الأعمدة عندها affinity
 * ديناميكي وليس نوعاً صارماً — عمود DATE موجود أصلاً يقبل تخزين نص "YYYY-MM-DD HH:MM:SS"
 * بدون أي مشكلة، فلا حاجة لإعادة بناء الجدول هون (بعكس migration توسيع enum الغرامات).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE tenders MODIFY deadline DATETIME NULL');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE tenders MODIFY deadline DATE NULL');
        }
    }
};
