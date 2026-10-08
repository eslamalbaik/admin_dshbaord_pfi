<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * صلاحيات لوحة التحكم: دور "مشرف" جديد بصلاحيات حسب الأقسام (App\Support\DashboardPermissions).
 *
 * إضافي فقط — ما بيغيّر دور أي حساب موجود: كل الحسابات الحالية بتاخد is_active = true
 * و permissions = null، والأدمن والمحاسب بيضلوا على صلاحياتهم الحالية.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','student','instructor','accountant','supervisor') NOT NULL DEFAULT 'student'");
        } elseif (DB::getDriverName() === 'sqlite') {
            // بيئة الاختبار: enum بـ sqlite هو CHECK constraint ما بيقبل الأدوار الجديدة
            // (ولا المحاسب) — بنحوّله لنص عادي
            Schema::table('users', function (Blueprint $table) {
                $table->string('role')->default('student')->change();
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->json('permissions')->nullable()->after('role');
            $table->boolean('is_active')->default(true)->after('permissions');
            $table->foreignId('created_by')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['permissions', 'is_active', 'created_by']);
        });

        if (DB::getDriverName() === 'mysql') {
            // المشرفين بيرجعوا لدور student (بدون أي صلاحية بلوحة التحكم) قبل تضييق الـ enum
            DB::table('users')->where('role', 'supervisor')->update(['role' => 'student']);
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','student','instructor','accountant') NOT NULL DEFAULT 'student'");
        }
    }
};
