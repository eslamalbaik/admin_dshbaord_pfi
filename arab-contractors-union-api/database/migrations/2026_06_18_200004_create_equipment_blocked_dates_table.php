<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_blocked_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment')->onDelete('cascade');
            $table->date('blocked_date');
            $table->string('reason')->nullable()->default('booked'); // booked | maintenance | other
            $table->timestamps();

            $table->unique(['equipment_id', 'blocked_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_blocked_dates');
    }
};
