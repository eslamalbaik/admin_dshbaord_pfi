<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_files', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('title_en')->nullable();
            $table->string('description')->nullable();
            $table->string('description_en')->nullable();
            // التصنيف: legislation | mou | other
            $table->enum('category', ['legislation', 'mou', 'other'])->default('other');
            $table->string('file_path');           // مسار الملف المخزّن
            $table->string('disk')->default('public');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable(); // بالبايت
            $table->integer('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();

            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_files');
    }
};
