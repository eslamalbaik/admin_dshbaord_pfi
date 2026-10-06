<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // كم سدّدت كل دفعة من كل ذمة — عشان "تغيير الحالة" يقدر يرجّع تسديد دفعة مؤكَّدة بدقة.
        // الدفعات الأقدم من هالجدول ما إلها سجل، فبضل ترجيعها ممنوع.
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('contractor_due_id')->constrained('contractor_dues')->cascadeOnDelete();
            $table->decimal('amount_jod', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
