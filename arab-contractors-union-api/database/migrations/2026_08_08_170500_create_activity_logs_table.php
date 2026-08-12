<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سجل تدقيق للعمليات الحساسة بلوحة التحكم (اعتماد/رفض دفعات، شهادات،
 * حظر مقاولين، الموافقة على طلبات تعديل بروفايل...) — توصية أفضل الممارسات.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_type')->nullable(); // App\Models\User عادة (موظف الاتحاد)
            $table->string('action'); // مثال: payment.confirmed, certificate.approved
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['actor_type', 'actor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
