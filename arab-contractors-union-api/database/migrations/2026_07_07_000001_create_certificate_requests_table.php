<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['membership', 'good_standing', 'classification', 'experience'])->default('membership');
            $table->enum('status', ['pending', 'approved', 'issued', 'rejected'])->default('pending');
            $table->text('notes')->nullable();
            $table->text('reject_reason')->nullable();
            $table->string('certificate_path')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['contractor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_requests');
    }
};
