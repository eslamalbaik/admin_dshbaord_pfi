<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // اسم الجدول app_notifications وليس notifications — الأخير محجوز لجدول Laravel
        // الافتراضي (uuid/morph) المستخدم لإشعارات الأدمن عبر قناة database. راجع
        // 2026_06_03_150943_create_notifications_table وموديل App\Models\Notification.
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained('contractors')->onDelete('cascade');
            $table->enum('type', ['announcement', 'payment_reminder', 'expiry_reminder'])->index();
            $table->string('title');
            $table->text('body');
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_type')->nullable();
            $table->string('action_url')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->foreignId('run_id')->nullable()->constrained('notification_runs')->onDelete('set null');
            $table->timestamps();

            // Indexes for efficient querying — 'type' is already indexed inline above
            // (->index() on the enum column); re-indexing it here duplicates the key name.
            $table->index(['contractor_id', 'created_at']);
            $table->index('run_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};
