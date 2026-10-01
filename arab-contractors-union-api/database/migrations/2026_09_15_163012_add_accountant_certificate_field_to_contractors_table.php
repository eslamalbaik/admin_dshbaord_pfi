<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * وثيقة مالية جديدة ضمن قائمة المستندات المطلوبة: شهادة تفرغ محاسب من نقابة
 * المحاسبين أو عقد مع مكتب محاسبين معتمد — نفس نمط full_time_engineer_certificate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contractors', function (Blueprint $table) {
            $table->string('accountant_certificate_or_contract')->nullable()->after('full_time_engineer_certificate');
        });
    }

    public function down(): void
    {
        Schema::table('contractors', function (Blueprint $table) {
            $table->dropColumn('accountant_certificate_or_contract');
        });
    }
};
