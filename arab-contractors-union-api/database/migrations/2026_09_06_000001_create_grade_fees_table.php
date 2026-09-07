<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * جدول رسوم الدرجات (المادة 37) — 6 صفوف ثابتة، قابلة للتعديل من لوحة التحكم بدل ثوابت بالكود،
 * حتى يستطيع مجلس الإدارة تعديل المبالغ دون نشر كود جديد (المادة 37/ت).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_fees', function (Blueprint $table) {
            $table->id();
            $table->string('grade_code', 20)->unique();
            $table->string('grade_label', 50);
            $table->unsignedTinyInteger('sort_order');
            $table->decimal('registration_fee_jod', 10, 2);
            $table->decimal('annual_fee_jod', 10, 2);
            $table->timestamps();
        });

        $now = now();

        DB::table('grade_fees')->insert([
            ['grade_code' => 'اولى أ', 'grade_label' => 'الدرجة الأولى (1)', 'sort_order' => 1, 'registration_fee_jod' => 700, 'annual_fee_jod' => 600, 'created_at' => $now, 'updated_at' => $now],
            ['grade_code' => 'اولى ب', 'grade_label' => 'الدرجة الأولى',     'sort_order' => 2, 'registration_fee_jod' => 500, 'annual_fee_jod' => 400, 'created_at' => $now, 'updated_at' => $now],
            ['grade_code' => 'ثانية',  'grade_label' => 'الدرجة الثانية',    'sort_order' => 3, 'registration_fee_jod' => 400, 'annual_fee_jod' => 300, 'created_at' => $now, 'updated_at' => $now],
            ['grade_code' => 'ثالثة',  'grade_label' => 'الدرجة الثالثة',    'sort_order' => 4, 'registration_fee_jod' => 300, 'annual_fee_jod' => 200, 'created_at' => $now, 'updated_at' => $now],
            ['grade_code' => 'رابعة',  'grade_label' => 'الدرجة الرابعة',    'sort_order' => 5, 'registration_fee_jod' => 200, 'annual_fee_jod' => 100, 'created_at' => $now, 'updated_at' => $now],
            ['grade_code' => 'خامسة',  'grade_label' => 'الدرجة الخامسة',    'sort_order' => 6, 'registration_fee_jod' => 100, 'annual_fee_jod' => 50,  'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_fees');
    }
};
