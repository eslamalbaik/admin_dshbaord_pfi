<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حقول وسائط إضافية للأخبار: رابط فيديو يوتيوب، رابط خارجي، ومعرض صور.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->string('video_url')->nullable()->after('image');    // رابط يوتيوب
            $table->string('external_url')->nullable()->after('video_url'); // رابط لموقع خارجي
            $table->json('gallery')->nullable()->after('external_url');  // صور إضافية
        });
    }

    public function down(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->dropColumn(['video_url', 'external_url', 'gallery']);
        });
    }
};
