<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** الإبلاغ عن مشكلة بإعلان آلية (اكتُشف بتصميم الموبايل، خارج نطاق وثيقة الاجتماع). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete(); // المُبلِغ
            $table->string('reason');
            $table->enum('status', ['pending', 'reviewed', 'dismissed'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_reports');
    }
};
