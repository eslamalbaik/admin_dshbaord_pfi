<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contractors', function (Blueprint $table) {
            // رقم الجوال أصبح معرّف تسجيل الدخول/التفعيل الأساسي — يجب أن يكون فريداً
            $table->unique('phone');
        });
    }

    public function down(): void
    {
        Schema::table('contractors', function (Blueprint $table) {
            $table->dropUnique(['phone']);
        });
    }
};
