<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->enum('type', ['license', 'id', 'contract', 'certificate', 'other'])->default('other');
            $table->string('url');                            // مسار الملف أو S3 URL
            $table->string('disk')->default('local');        // local | s3
            $table->unsignedBigInteger('size')->default(0);  // بالبايت
            $table->string('mime_type')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
