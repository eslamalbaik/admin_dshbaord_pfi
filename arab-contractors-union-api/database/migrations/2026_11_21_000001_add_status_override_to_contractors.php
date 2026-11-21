<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * اختيار الإدارة اليدوي لحالة العضوية (فعّالة/منتهية) من قائمة المقاولين بيغلب الحالة المحسوبة من
 * الرصيد لنفس السنة بس — بأول سنة جديدة بيرجع الحساب الآلي لحاله.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contractors', function (Blueprint $table) {
            $table->string('status_override', 20)->nullable()->after('status');
            $table->unsignedSmallInteger('status_override_year')->nullable()->after('status_override');
        });
    }

    public function down(): void
    {
        Schema::table('contractors', function (Blueprint $table) {
            $table->dropColumn(['status_override', 'status_override_year']);
        });
    }
};
