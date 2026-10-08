<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تتبّع فتح المقاول لشهادته الصادرة — اللوحة بتعرض للأدمن إذا وصلت الشهادة للمقاول
 * وإذا فتحها. الفتح بينحسب من رابط الشهادة اللي بيستلمه المقاول (رابط موقَّع بيمرّ على
 * السيرفر)، مش من رابط التخزين المباشر اللي بتستخدمه اللوحة نفسها.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_requests', function (Blueprint $table) {
            $table->timestamp('viewed_at')->nullable()->after('issued_at');
            $table->timestamp('last_viewed_at')->nullable()->after('viewed_at');
            $table->unsignedInteger('views_count')->default(0)->after('last_viewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('certificate_requests', function (Blueprint $table) {
            $table->dropColumn(['viewed_at', 'last_viewed_at', 'views_count']);
        });
    }
};
