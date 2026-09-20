<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * إضافة 'tender' لقائمة أنواع الوثائق — يدعم قسم "إدارة وثائق العطاءات"
 * (REQ-Tender-Docs): وثائق عطاء لكل شركة، مرفوعة من الإدارة أو المقاول.
 * تعديل ENUM عبر SQL خام لأن Doctrine DBAL لا يدعم تغيير ENUM مباشرة.
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL فقط — sqlite بيئة الاختبار لا تدعم MODIFY COLUMN (وأعمدتها بلا قيد enum أصلاً)
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE documents MODIFY COLUMN type ENUM('license','id','contract','certificate','tender','other') NOT NULL DEFAULT 'other'"
            );
        }
    }

    public function down(): void
    {
        DB::statement(
            "UPDATE documents SET type = 'other' WHERE type = 'tender'"
        );

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE documents MODIFY COLUMN type ENUM('license','id','contract','certificate','other') NOT NULL DEFAULT 'other'"
            );
        }
    }
};
