<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** سجل محادثات المساعد الافتراضي بالموقع العام (للمراجعة من لوحة التحكم) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('session_key', 64)->unique();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('page', 255)->nullable();
            $table->unsignedInteger('messages_count')->default(0);
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('assistant_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assistant_conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role', 10);   // user | bot
            $table->text('text');
            $table->string('source', 20)->nullable(); // ai | faq | smalltalk | fallback
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_messages');
        Schema::dropIfExists('assistant_conversations');
    }
};
