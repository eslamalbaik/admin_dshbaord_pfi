<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حجوزات فعلية للآليات من داخل التطبيق (REQ-08 #6 — توسيع من EquipmentBlockedDate
 * الذي كان مجرد أداة حجب تواريخ يدوية بلا هوية مستأجر). المقاول المستأجر يطلب فترة،
 * وتُقبل تلقائياً فوراً إن كانت متاحة (بدون خطوة موافقة من المالك/الإدارة) — قرار منتجي.
 * EquipmentBlockedDate يبقى منفصلاً لحجب الأدمن اليدوي (صيانة/أسباب أخرى)، والتحقق من
 * التوفر يفحص الاثنين معاً.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $table->foreignId('contractor_id')->constrained('contractors')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['confirmed', 'cancelled'])->default('confirmed');
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['equipment_id', 'status', 'start_date', 'end_date'], 'equipment_reservations_availability_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_reservations');
    }
};
