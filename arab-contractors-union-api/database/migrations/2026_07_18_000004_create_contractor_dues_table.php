<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contractor_dues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('year')->nullable();          // سنة الاستحقاق (للرسوم القديمة)
            $table->string('period', 50)->nullable();          // وصف فترة حر (مثل "2/2022")
            $table->string('description', 500);
            $table->decimal('amount_jod', 10, 2);              // قيمة الذمة بالدينار الأردني
            $table->decimal('paid_jod', 10, 2)->default(0);    // المسدَّد منها بالدينار
            $table->enum('status', ['unpaid', 'partially_paid', 'paid'])->default('unpaid');
            $table->enum('source', ['legacy_import', 'manual'])->default('manual');
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['contractor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contractor_dues');
    }
};
