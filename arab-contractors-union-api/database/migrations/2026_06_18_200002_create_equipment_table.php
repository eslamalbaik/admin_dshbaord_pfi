<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained('contractors')->onDelete('cascade');
            $table->foreignId('equipment_type_id')->constrained('equipment_types')->onDelete('restrict');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('manufacture_year')->nullable();
            $table->string('power')->nullable();          // e.g. "250 HP"
            $table->enum('condition', ['excellent', 'good', 'fair'])->default('good');
            $table->string('governorate')->nullable();
            $table->string('city')->nullable();
            $table->decimal('daily_price', 10, 2)->default(0);
            $table->string('owner_phone')->nullable();
            // visible = ظاهر، hidden = مخفي بقرار المالك، suspended = موقوف بقرار المشرف
            $table->enum('status', ['visible', 'hidden', 'suspended'])->default('visible');
            $table->text('admin_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment');
    }
};
