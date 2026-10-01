<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // أرصدة دائنة للشركات لدى الاتحاد (دفعات مقدّمة) — منفصلة عن payments حتى لا تُحسب كإيرادات
        Schema::create('contractor_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount_jod', 10, 2);
            $table->decimal('used_jod', 10, 2)->default(0);
            $table->string('description', 500);
            $table->enum('source', ['statement_import', 'manual'])->default('manual');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('contractor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contractor_credits');
    }
};
