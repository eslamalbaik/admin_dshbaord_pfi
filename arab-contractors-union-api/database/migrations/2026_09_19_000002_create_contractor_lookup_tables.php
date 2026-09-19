<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * جداول المرجعية القابلة للإدارة من لوحة الأدمن للمجالات/الاختصاصات/الدرجات (REQ-01 #7) —
 * تحل محل الثوابت الجامدة في ContractorLookups مع إبقاء نفس الأكواد الرقمية/النصية المستخدمة
 * فعلياً بجدول contractors.specialties (JSON) وبمحرك حساب الرسوم، تفادياً لكسر أي بيانات قائمة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contractor_fields', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('code')->unique();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('contractor_specializations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('code')->unique();
            $table->foreignId('contractor_field_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('contractor_grades', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // القيمة المخزّنة فعلياً بـ specialties[].classification، مثال: "اولى أ"
            $table->string('label');
            $table->unsignedInteger('level'); // الأقل = الأعلى درجة
            $table->json('eligible_field_codes')->nullable(); // null = تصلح لكل المجالات
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();

        $fields = [
            10 => 'غير محدد', 20 => 'طرق', 30 => 'ابنية',
            40 => 'كهروميكانيك', 50 => 'الميــاه/المجــارى', 60 => 'أشغال عامه',
        ];
        DB::table('contractor_fields')->insert(
            collect($fields)->map(fn ($name, $code) => [
                'code' => $code, 'name' => $name, 'sort_order' => $code,
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ])->values()->all()
        );

        $specializations = [
            10 => 'غير محدد', 20 => 'الطرق', 30 => 'خلطات اسفلتيه', 40 => 'خرسانه جسور وعبارات',
            50 => 'اشغال ترابيه', 60 => 'الأبنية', 70 => 'خرسانه مصنعه', 80 => 'منشأت معدنية',
            90 => 'أبنية جاهزه بريفاف', 100 => 'صيانة الابنيه', 110 => 'كهروميكانيك',
            120 => 'صيانة كهروميكانيك', 130 => 'ميكانيك', 140 => 'كـهرباء', 150 => 'الكترونيات',
            160 => 'المياه والمجاري', 170 => 'محطات التنقيه', 180 => 'الري والصرف',
            190 => 'حفريات وتعدين', 200 => 'اشغال عامه', 210 => 'سكك حديدية', 220 => 'حفر آبار',
        ];
        // ربط كل اختصاص بمجاله — نفس الخريطة المستخدمة سابقاً بـ ContractorLookups::FIELD_SPECIALIZATIONS
        $fieldOf = [
            20 => 20, 30 => 20, 40 => 20, 50 => 20, 210 => 20,       // طرق
            60 => 30, 70 => 30, 80 => 30, 90 => 30, 100 => 30,       // ابنية
            110 => 40, 120 => 40, 130 => 40, 140 => 40, 150 => 40,   // كهروميكانيك
            160 => 50, 170 => 50, 180 => 50, 220 => 50,              // الميـاه/المجـارى
            190 => 60, 200 => 60,                                    // أشغال عامه
        ];
        $fieldIdByCode = DB::table('contractor_fields')->pluck('id', 'code');
        DB::table('contractor_specializations')->insert(
            collect($specializations)->map(fn ($name, $code) => [
                'code' => $code, 'name' => $name,
                'contractor_field_id' => isset($fieldOf[$code]) ? ($fieldIdByCode[$fieldOf[$code]] ?? null) : null,
                'sort_order' => $code, 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ])->values()->all()
        );

        $grades = [
            ['code' => 'اولى أ', 'label' => 'الدرجة الأولى (أ)', 'level' => 1, 'eligible_field_codes' => [20, 30]],
            ['code' => 'اولى ب', 'label' => 'الدرجة الأولى (ب)', 'level' => 2, 'eligible_field_codes' => null],
            ['code' => 'ثانية',  'label' => 'الدرجة الثانية',    'level' => 3, 'eligible_field_codes' => null],
            ['code' => 'ثالثة',  'label' => 'الدرجة الثالثة',    'level' => 4, 'eligible_field_codes' => null],
            ['code' => 'رابعة',  'label' => 'الدرجة الرابعة',    'level' => 5, 'eligible_field_codes' => null],
            ['code' => 'خامسة',  'label' => 'الدرجة الخامسة',    'level' => 6, 'eligible_field_codes' => null],
        ];
        DB::table('contractor_grades')->insert(
            collect($grades)->map(fn ($g, $i) => [
                'code' => $g['code'], 'label' => $g['label'], 'level' => $g['level'],
                'eligible_field_codes' => $g['eligible_field_codes'] ? json_encode($g['eligible_field_codes']) : null,
                'sort_order' => $i, 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ])->values()->all()
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('contractor_grades');
        Schema::dropIfExists('contractor_specializations');
        Schema::dropIfExists('contractor_fields');
    }
};
