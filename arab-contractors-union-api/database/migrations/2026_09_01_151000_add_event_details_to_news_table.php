<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** حقول إضافية لشاشة "تفاصيل الفعالية" (category=event): نوع الحضور، رابط البث، المتحدثون. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->enum('event_format', ['onsite', 'online', 'hybrid'])->nullable()->after('event_location');
            $table->boolean('is_international')->default(false)->after('event_format');
            $table->string('stream_url')->nullable()->after('is_international');
            $table->json('speakers')->nullable()->after('stream_url');
        });
    }

    public function down(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->dropColumn(['event_format', 'is_international', 'stream_url', 'speakers']);
        });
    }
};
