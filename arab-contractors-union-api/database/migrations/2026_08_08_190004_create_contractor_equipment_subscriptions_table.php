<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** اشتراك مقاول فعّال بسوق الآليات — يُنشأ/يُفعَّل تلقائياً عند تأكيد دفعة type=equipment_subscription. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contractor_equipment_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('expires_at');
            $table->boolean('is_free_trial')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contractor_equipment_subscriptions');
    }
};
