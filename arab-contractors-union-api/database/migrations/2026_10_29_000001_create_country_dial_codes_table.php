<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * مقدمات الدول (+970 وأخواتها) اللي بتظهر قائمة جنب خانات الجوال في اللوحة — بيديرها الأدمن من
 * الإعدادات، ومعها قاعدة طول الرقم المحلي وأول رقم مسموح لكل دولة (للتحقق في الواجهة والخادم).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('country_dial_codes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('iso_code', 2)->nullable();
            $table->string('dial_code', 6)->unique();      // أرقام فقط بدون + (970)
            $table->string('flag_path')->nullable();       // علم مرفوع يدوياً (وإلا من iso_code)
            $table->unsignedTinyInteger('min_digits')->default(7);
            $table->unsignedTinyInteger('max_digits')->default(12);
            $table->string('starts_with', 30)->nullable(); // أول رقم مسموح بعد المقدمة، مفصولة بفواصل (5 أو 3,5,6)
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        $now = now();
        $rows = [
            ['فلسطين', 'PS', '970', 9, 9, '5', true],
            ['فلسطين (972)', 'PS', '972', 9, 9, '5', false],
            ['الأردن', 'JO', '962', 9, 9, '7', false],
            ['مصر', 'EG', '20', 10, 10, '1', false],
            ['السعودية', 'SA', '966', 9, 9, '5', false],
            ['الإمارات', 'AE', '971', 9, 9, '5', false],
            ['قطر', 'QA', '974', 8, 8, '3,5,6,7', false],
            ['الكويت', 'KW', '965', 8, 8, '5,6,9', false],
            ['البحرين', 'BH', '973', 8, 8, '3', false],
            ['عُمان', 'OM', '968', 8, 8, '7,9', false],
            ['لبنان', 'LB', '961', 7, 8, null, false],
            ['تركيا', 'TR', '90', 10, 10, '5', false],
        ];

        DB::table('country_dial_codes')->insert(array_map(fn ($r, $i) => [
            'name'        => $r[0],
            'iso_code'    => $r[1],
            'dial_code'   => $r[2],
            'min_digits'  => $r[3],
            'max_digits'  => $r[4],
            'starts_with' => $r[5],
            'is_default'  => $r[6],
            'is_active'   => true,
            'sort'        => $i + 1,
            'created_at'  => $now,
            'updated_at'  => $now,
        ], $rows, array_keys($rows)));
    }

    public function down(): void
    {
        Schema::dropIfExists('country_dial_codes');
    }
};
