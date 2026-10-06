<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // حالة العضوية (وحالة المقاول) قبل ما دفعة رسوم العضوية تجدّدها — عشان "تغيير الحالة"
        // يقدر يرجّعها زي ما كانت لو الدفعة رجعت لقيد المراجعة أو انرفضت
        Schema::table('payments', function (Blueprint $table) {
            $table->json('membership_snapshot')->nullable()->after('membership_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('membership_snapshot');
        });
    }
};
