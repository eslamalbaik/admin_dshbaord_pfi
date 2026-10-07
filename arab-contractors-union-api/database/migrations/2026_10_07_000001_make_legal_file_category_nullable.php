<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ملفات بدون تصنيف بعد حذف تصنيفها
        if (DB::getDriverName() === 'sqlite') {
            // SQLite (بيئة التستات) ما بيدعم MODIFY — change() بيعيد بناء الجدول
            Schema::table('legal_files', fn (Blueprint $table) => $table->string('category', 50)->nullable()->default(null)->change());

            return;
        }

        DB::statement('ALTER TABLE legal_files MODIFY category VARCHAR(50) NULL DEFAULT NULL');
    }

    public function down(): void
    {
        DB::table('legal_files')->whereNull('category')->update(['category' => 'other']);

        if (DB::getDriverName() === 'sqlite') {
            Schema::table('legal_files', fn (Blueprint $table) => $table->string('category', 50)->default('other')->change());

            return;
        }

        DB::statement("ALTER TABLE legal_files MODIFY category VARCHAR(50) NOT NULL DEFAULT 'other'");
    }
};
