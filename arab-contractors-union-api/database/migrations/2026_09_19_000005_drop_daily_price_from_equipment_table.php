<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حذف حقل daily_price نهائياً (REQ-08 #7 بالشيت) — قرار منتجي: الحقل غير واضح الغرض
 * وغير مستخدَم فعلياً، وليس له بديل (contract_type يبقى كما هو — يمثّل نوع مدة العقد
 * وليس السعر نفسه).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            if (Schema::hasColumn('equipment', 'daily_price'))
                $table->dropColumn('daily_price');
        });
    }

    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            if (! Schema::hasColumn('equipment', 'daily_price'))
                $table->decimal('daily_price', 10, 2)->default(0);
        });
    }
};
