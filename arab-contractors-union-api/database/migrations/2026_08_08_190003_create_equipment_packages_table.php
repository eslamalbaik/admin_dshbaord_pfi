<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** REQ-06: باقات اشتراك سوق الآليات (بعد انتهاء الفترة التجريبية المجانية). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('JOD');
            $table->unsignedInteger('duration_days');
            $table->unsignedInteger('ads_limit')->nullable(); // فارغ = بلا حد
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_packages');
    }
};
