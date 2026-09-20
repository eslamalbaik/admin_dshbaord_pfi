<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Delete legacy LMS users (student, instructor)
        DB::table('users')
            ->whereIn('role', ['student', 'instructor'])
            ->delete();

        // 2. Alter the 'role' column enum to only allow 'admin' and 'accountant'
        // Doctrine DBAL (used by Laravel Schema builder for altering) sometimes has issues with ENUMs.
        // It's safer to use a raw DB statement for ENUM alterations.
        // MySQL فقط — sqlite بيئة الاختبار لا تدعم MODIFY COLUMN فتفشل كل الاختبارات عند الترحيل
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'accountant') NOT NULL DEFAULT 'admin'");
        }

        // 3. Drop 'total_students' column from 'system_statistics' table
        if (Schema::hasColumn('system_statistics', 'total_students')) {
            Schema::table('system_statistics', function (Blueprint $table) {
                $table->dropColumn('total_students');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'accountant', 'student', 'instructor') NOT NULL DEFAULT 'admin'");
        }

        if (! Schema::hasColumn('system_statistics', 'total_students')) {
            Schema::table('system_statistics', function (Blueprint $table) {
                $table->unsignedInteger('total_students')->default(0)->after('id');
            });
        }
    }
};
