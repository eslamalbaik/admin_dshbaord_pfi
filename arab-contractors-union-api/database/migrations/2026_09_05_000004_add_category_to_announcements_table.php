<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** شاشة "التعميمات": تبويب تصنيف حر (تعميمات الشؤون الفنية والتصنيف...) بجانب تبويب "عاجل وهام" (is_pinned). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->string('category')->nullable()->after('number');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
