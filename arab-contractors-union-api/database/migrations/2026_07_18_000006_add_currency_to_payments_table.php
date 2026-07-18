<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // عملة الدفع الفعلية — الأساس المحاسبي يبقى الدينار الأردني
            $table->string('currency', 3)->default('JOD')->after('amount');
            // سعر الصرف والمعادل بالدينار يُثبَّتان لحظة تأكيد المحاسب
            $table->decimal('exchange_rate', 12, 6)->nullable()->after('currency');
            $table->decimal('amount_jod', 10, 2)->nullable()->after('exchange_rate');
            $table->string('rate_source', 20)->nullable()->after('amount_jod'); // api | manual
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['currency', 'exchange_rate', 'amount_jod', 'rate_source']);
        });
    }
};
