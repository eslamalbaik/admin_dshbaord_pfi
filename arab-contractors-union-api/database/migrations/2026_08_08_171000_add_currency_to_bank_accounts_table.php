<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * دعم عدة عملات لكل بنك (شيكل/دينار/دولار/يورو) — سجل منفصل لكل عملة
 * بدل تصميم جدول جديد، مطابقةً لهيكل bank_accounts الحالي.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->enum('currency', ['ILS', 'JOD', 'USD', 'EUR'])->default('JOD')->after('bank_name_en');
        });
    }

    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
