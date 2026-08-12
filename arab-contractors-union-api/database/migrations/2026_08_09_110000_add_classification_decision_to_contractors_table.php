<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * رقم/تاريخ قرار لجنة التصنيف الوطنية — يظهر بنص شهادة العضوية المُصدَرة تلقائياً.
 * إدخال يدوي من الإدارة (اللجان متوقفة فعلياً منذ 8/2023 — قرار استثنائي لكل مقاول).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contractors', function (Blueprint $table) {
            $table->string('classification_decision_number')->nullable()->after('classification');
            $table->date('classification_decision_date')->nullable()->after('classification_decision_number');
        });
    }

    public function down(): void
    {
        Schema::table('contractors', function (Blueprint $table) {
            $table->dropColumn(['classification_decision_number', 'classification_decision_date']);
        });
    }
};
