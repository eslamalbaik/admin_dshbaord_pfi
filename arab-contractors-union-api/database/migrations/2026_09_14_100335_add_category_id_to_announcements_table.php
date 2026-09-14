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
            // category (نصي حر) يبقى مؤقتاً للتوافق الخلفي مع فلترة/تجميع index() الحالية —
            // يُزامَن تلقائياً من اسم التصنيف المُدار عند اختيار category_id (AnnouncementController)
            $table->foreignId('category_id')->nullable()->after('category')
                ->constrained('announcement_categories')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
    }
};
