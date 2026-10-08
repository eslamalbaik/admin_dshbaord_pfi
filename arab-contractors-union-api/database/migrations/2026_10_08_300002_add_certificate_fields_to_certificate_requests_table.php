<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * بيانات شهادة العضوية اللي بيدخلها الأدمن عند الموافقة على الطلب أو إصداره (العنوان،
 * رقم وتاريخ قرار لجنة التصنيف) — بتنحفظ على الطلب نفسه، فالإصدار التلقائي وإعادة
 * الإصدار بيطبعوا نفس القيم بدل ما يرجعوا لملف المقاول.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_requests', function (Blueprint $table) {
            $table->string('certificate_address')->nullable()->after('attachment');
            $table->string('decision_number', 100)->nullable()->after('certificate_address');
            $table->date('decision_date')->nullable()->after('decision_number');
        });
    }

    public function down(): void
    {
        Schema::table('certificate_requests', function (Blueprint $table) {
            $table->dropColumn(['certificate_address', 'decision_number', 'decision_date']);
        });
    }
};
