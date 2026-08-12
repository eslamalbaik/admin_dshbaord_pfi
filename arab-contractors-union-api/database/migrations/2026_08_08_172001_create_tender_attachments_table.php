<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** مرفقات متعددة للعطاء (اكتُشف بتصميم الموبايل) — إضافة موازية لعمود submission_file الحالي. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tender_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tender_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('label')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tender_attachments');
    }
};
