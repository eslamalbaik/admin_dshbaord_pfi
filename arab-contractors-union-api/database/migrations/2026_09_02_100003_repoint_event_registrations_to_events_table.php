<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->unsignedBigInteger('event_id')->nullable()->after('news_id');
        });

        DB::statement('UPDATE event_registrations SET event_id = news_id');

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropForeign(['news_id']);
            $table->dropUnique(['news_id', 'contractor_id']);
            $table->dropColumn('news_id');
        });

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->unsignedBigInteger('event_id')->nullable(false)->change();
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->unique(['event_id', 'contractor_id']);
        });
    }

    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->unsignedBigInteger('news_id')->nullable()->after('event_id');
        });

        DB::statement('UPDATE event_registrations SET news_id = event_id');

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropForeign(['event_id']);
            $table->dropUnique(['event_id', 'contractor_id']);
            $table->dropColumn('event_id');
        });

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->unsignedBigInteger('news_id')->nullable(false)->change();
            $table->foreign('news_id')->references('id')->on('news')->cascadeOnDelete();
            $table->unique(['news_id', 'contractor_id']);
        });
    }
};
