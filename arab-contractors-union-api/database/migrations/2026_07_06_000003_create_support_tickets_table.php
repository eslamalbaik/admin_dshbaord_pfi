<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تذاكر الدعم الفني والشكاوى — يرسلها المقاول من شاشة "حسابي"
 * وترد عليها الإدارة عبر البريد الإلكتروني وإشعار داخل التطبيق.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->string('subject');
            $table->enum('category', ['technical', 'complaint', 'inquiry', 'suggestion', 'other'])
                  ->default('technical');
            $table->text('message');
            $table->string('attachment')->nullable();          // مرفق اختياري من المقاول
            $table->enum('status', ['open', 'in_progress', 'answered', 'closed'])
                  ->default('open');
            $table->text('reply')->nullable();                 // رد الإدارة
            $table->foreignId('replied_by')->nullable()
                  ->constrained('users')->nullOnDelete();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();

            $table->index(['contractor_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
