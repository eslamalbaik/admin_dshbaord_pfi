<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('body');
            $table->string('image')->nullable();
            $table->string('video_url')->nullable();
            $table->string('external_url')->nullable();
            $table->json('gallery')->nullable();
            $table->dateTime('event_date')->nullable();
            $table->string('event_location')->nullable();
            $table->enum('event_format', ['onsite', 'online', 'hybrid'])->nullable();
            $table->boolean('is_international')->default(false);
            $table->string('stream_url')->nullable();
            $table->json('speakers')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
