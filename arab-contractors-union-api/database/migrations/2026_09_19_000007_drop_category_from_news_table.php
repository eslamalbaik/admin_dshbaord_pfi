<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حذف عمود category من الأخبار نهائياً (REQ-10 #1) — قرار منتجي. كان enum من عهد
 * قبل فصل الإعلانات/الفعاليات/العطاءات لجداولها الخاصة، وصار مقفولاً على قيمة 'news'
 * فقط بمنطق التطبيق (validate: required|in:news) — عمود بلا فائدة فعلية، وتبويب
 * الفلترة به بالموقع العام كان معطوباً بصمت (يعرض صفر نتائج لأي قيمة غير news).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news', function (Blueprint $table) {
            if (Schema::hasColumn('news', 'category'))
                $table->dropColumn('category');
        });
    }

    public function down(): void
    {
        Schema::table('news', function (Blueprint $table) {
            if (! Schema::hasColumn('news', 'category'))
                $table->enum('category', ['news', 'announcement', 'event', 'tender'])->default('news');
        });
    }
};
