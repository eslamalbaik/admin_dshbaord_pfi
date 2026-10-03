<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_file_categories', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('label');
            $table->string('label_en')->nullable();
            $table->integer('sort')->default(0);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        $now = now();
        DB::table('legal_file_categories')->insert([
            ['key' => 'legislation', 'label' => 'تشريعات',      'label_en' => 'Legislation', 'sort' => 1, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'mou',         'label' => 'مذكرات تفاهم', 'label_en' => 'MoUs',        'sort' => 2, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'other',       'label' => 'أخرى',         'label_en' => 'Other',       'sort' => 3, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // التصنيف صار مفتوحاً (تصنيفات مخصصة) بدل enum ثابت
        DB::statement("ALTER TABLE legal_files MODIFY category VARCHAR(50) NOT NULL DEFAULT 'other'");
    }

    public function down(): void
    {
        DB::table('legal_files')->whereNotIn('category', ['legislation', 'mou', 'other'])->update(['category' => 'other']);
        DB::statement("ALTER TABLE legal_files MODIFY category ENUM('legislation','mou','other') NOT NULL DEFAULT 'other'");
        Schema::dropIfExists('legal_file_categories');
    }
};
