<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['new', 'renewal', 'upgrade'])->default('new');
            $table->enum('status', ['pending', 'active', 'expired', 'rejected'])->default('pending');
            $table->date('starts_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('document_url')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
