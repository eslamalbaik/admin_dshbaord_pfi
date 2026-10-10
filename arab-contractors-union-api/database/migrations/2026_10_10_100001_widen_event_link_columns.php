<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// روابط الفعالية كانت varchar(255) بينما التحقق بيسمح بـ500 — رابط Zoom طويل كان يعدّي التحقق
// ويوقع بخطأ 500 من قاعدة البيانات. صارت text بلا قيد طول عملي (بند 18).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->text('video_url')->nullable()->change();
            $table->text('external_url')->nullable()->change();
            $table->text('stream_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('video_url')->nullable()->change();
            $table->string('external_url')->nullable()->change();
            $table->string('stream_url')->nullable()->change();
        });
    }
};
