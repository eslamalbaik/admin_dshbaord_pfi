<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // الدفعة اليدوية من شاشة الذمم بتسدّد الغرامات كمان بعد الذمم، فالتوزيع صار إما على ذمة أو على غرامة
        Schema::table('payment_allocations', function (Blueprint $table) {
            $table->unsignedBigInteger('contractor_due_id')->nullable()->change();
            $table->foreignId('penalty_id')->nullable()->after('contractor_due_id')->constrained('penalties')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_allocations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('penalty_id');
        });
    }
};
