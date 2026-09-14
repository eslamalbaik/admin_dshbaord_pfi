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
        DB::statement("ALTER TABLE equipment MODIFY `condition` ENUM('excellent','good','fair','needs_maintenance') DEFAULT 'good'");

        DB::table('equipment')->where('condition', 'fair')->update(['condition' => 'needs_maintenance']);

        DB::statement("ALTER TABLE equipment MODIFY `condition` ENUM('excellent','good','needs_maintenance') DEFAULT 'good'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE equipment MODIFY `condition` ENUM('excellent','good','fair','needs_maintenance') DEFAULT 'good'");

        DB::table('equipment')->where('condition', 'needs_maintenance')->update(['condition' => 'fair']);

        DB::statement("ALTER TABLE equipment MODIFY `condition` ENUM('excellent','good','fair') DEFAULT 'good'");
    }
};
