<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            // رابط خارجي اختياري للتعميم (صفحة/نموذج/ملف على موقع آخر) — التطبيق يعرضه كزر فتح رابط
            $table->text('external_url')->nullable()->after('attachment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn('external_url');
        });
    }
};
