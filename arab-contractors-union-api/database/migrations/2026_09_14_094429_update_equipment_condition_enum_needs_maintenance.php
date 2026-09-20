<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // خطوة وسيطة: نضيف needs_maintenance للقائمة مع إبقاء fair مؤقتاً، حتى ننقل البيانات القديمة بأمان
        // `condition` كلمة محجوزة بـ MySQL/MariaDB — لازم backticks
        // MySQL فقط — sqlite بيئة الاختبار لا تدعم MODIFY COLUMN (نقل البيانات يبقى للمحرّكين)
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE equipment MODIFY `condition` ENUM('excellent','good','fair','needs_maintenance') DEFAULT 'good'");
        }

        DB::table('equipment')->where('condition', 'fair')->update(['condition' => 'needs_maintenance']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE equipment MODIFY `condition` ENUM('excellent','good','needs_maintenance') DEFAULT 'good'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE equipment MODIFY `condition` ENUM('excellent','good','fair','needs_maintenance') DEFAULT 'good'");
        }

        DB::table('equipment')->where('condition', 'needs_maintenance')->update(['condition' => 'fair']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE equipment MODIFY `condition` ENUM('excellent','good','fair') DEFAULT 'good'");
        }
    }
};
