<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // تعديل الـ enum لإضافة دور المحاسب
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','student','instructor','accountant') NOT NULL DEFAULT 'student'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','student','instructor') NOT NULL DEFAULT 'student'");
    }
};
