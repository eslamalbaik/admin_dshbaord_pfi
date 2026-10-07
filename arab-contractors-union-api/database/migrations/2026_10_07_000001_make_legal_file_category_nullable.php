<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ملفات بدون تصنيف بعد حذف تصنيفها
        DB::statement('ALTER TABLE legal_files MODIFY category VARCHAR(50) NULL DEFAULT NULL');
    }

    public function down(): void
    {
        DB::table('legal_files')->whereNull('category')->update(['category' => 'other']);
        DB::statement("ALTER TABLE legal_files MODIFY category VARCHAR(50) NOT NULL DEFAULT 'other'");
    }
};
