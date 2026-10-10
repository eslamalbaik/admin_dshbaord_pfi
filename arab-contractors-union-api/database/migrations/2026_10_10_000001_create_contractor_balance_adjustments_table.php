<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // سجل تعديلات الرصيد اليدوية من صفحة أرصدة المقاولين: الرصيد قبل/بعد، السبب، ومين عدّل.
        // التعديل نفسه بيتنفّذ كرصيد دائن (زيادة) أو ذمة (إنقاص)، وهون بنحفظ الرابط عليهم.
        Schema::create('contractor_balance_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->string('mode', 20); // increase | decrease | set
            $table->decimal('amount_jod', 12, 2); // قيمة التغيير بإشارتها (+ له، − عليه)
            $table->decimal('balance_before_jod', 12, 2);
            $table->decimal('balance_after_jod', 12, 2);
            $table->string('status_before', 20)->nullable();
            $table->string('status_after', 20)->nullable();
            $table->string('reason', 500);
            $table->foreignId('contractor_credit_id')->nullable()->constrained('contractor_credits')->nullOnDelete();
            $table->foreignId('contractor_due_id')->nullable()->constrained('contractor_dues')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['contractor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contractor_balance_adjustments');
    }
};
