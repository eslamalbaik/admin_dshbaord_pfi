<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // عدّاد مشاهدات فيديوهات الموقع العام (مثل فيديو شرح تسجيل الدخول بالهيرو)
        Schema::create('site_video_views', function (Blueprint $table) {
            $table->id();
            $table->string('video_key', 64)->unique();
            $table->unsignedBigInteger('views')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_video_views');
    }
};
