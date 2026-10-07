<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // تحويل من التطبيق لدفع غرامة محددة (كرت الغرامة بالمستحقات) — متل contractor_due_id للذمم
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('penalty_id')->nullable()->after('contractor_due_id')->constrained('penalties')->nullOnDelete();
        });

        // الرصيد الدائن (contractor_credits) صار ينصرف تلقائياً على الذمم الجديدة، فالتوزيع إما من دفعة
        // أو من رصيد دائن — بنفس السجل حتى كشف الحساب يعرض كل تسديد ومصدره
        Schema::table('payment_allocations', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_id')->nullable()->change();
            $table->foreignId('contractor_credit_id')->nullable()->after('payment_id')->constrained('contractor_credits')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_allocations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contractor_credit_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('penalty_id');
        });
    }
};
