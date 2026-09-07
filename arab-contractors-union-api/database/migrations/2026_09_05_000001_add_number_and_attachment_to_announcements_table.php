<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** تعميم هام وعاجل: رقم التعميم + مرفق (PDF/مستند) للتفاصيل الكاملة. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->string('number')->nullable()->after('title');
            $table->string('attachment')->nullable()->after('image');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn(['number', 'attachment']);
        });
    }
};
