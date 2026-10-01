<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** يسدّ فجوة بين تصميم تطبيق الموبايل وبيانات سوق الآليات: الماركة، نوع العقد، بادج "مميزة"، وحالة "بحاجة صيانة". */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->string('brand')->nullable()->after('equipment_type_id');
            $table->enum('contract_type', ['daily', 'weekly', 'monthly'])->default('daily')->after('condition');
            $table->boolean('is_featured')->default(false)->after('status');
            // مستقل عن condition (تصنيف جودة ثابت) — علم مؤقت يقدر المالك يفعّله/يلغيه وقت ما الآلية معطوبة فعلياً
            $table->boolean('needs_maintenance')->default(false)->after('is_featured');
        });
    }

    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->dropColumn(['brand', 'contract_type', 'is_featured', 'needs_maintenance']);
        });
    }
};
