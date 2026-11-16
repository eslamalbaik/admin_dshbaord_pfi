<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تصنيف الأخبار (مراجعة لغوية 2026-11-16): أخبار الاتحاد / محلية / دولية — للتبويبات بالموقع العام.
 * ليس عودة للـ enum القديم (news/announcement/event/tender)؛ الأخبار الحالية كلها تصير "union".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news', function (Blueprint $table) {
            if (! Schema::hasColumn('news', 'category'))
                $table->enum('category', ['union', 'local', 'international'])->default('union')->after('excerpt');
        });
    }

    public function down(): void
    {
        Schema::table('news', function (Blueprint $table) {
            if (Schema::hasColumn('news', 'category'))
                $table->dropColumn('category');
        });
    }
};
