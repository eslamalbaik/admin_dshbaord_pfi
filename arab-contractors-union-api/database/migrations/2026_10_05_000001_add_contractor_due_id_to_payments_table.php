<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ربط تحويل التطبيق بالذمة اللي انضغط عليها "ادفع الآن" — ليظهر سبب الرفض
        // وحالة "قيد المراجعة" على كرت الذمة نفسه، وتُسدَّد الذمة تلقائياً عند الاعتماد
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('contractor_due_id')->nullable()->after('equipment_package_id')
                ->constrained('contractor_dues')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contractor_due_id');
        });
    }
};
