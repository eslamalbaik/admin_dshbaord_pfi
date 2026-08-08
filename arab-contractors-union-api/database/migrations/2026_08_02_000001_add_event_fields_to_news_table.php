<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حقول خاصة بالمناسبات (category = event): موعد الفعالية ومكانها.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->dateTime('event_date')->nullable()->after('gallery');
            $table->string('event_location')->nullable()->after('event_date');
        });
    }

    public function down(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->dropColumn(['event_date', 'event_location']);
        });
    }
};
