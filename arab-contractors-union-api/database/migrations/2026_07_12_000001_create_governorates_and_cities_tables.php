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
        Schema::create('governorates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('governorate_id')->constrained('governorates')->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['governorate_id', 'name']);
        });

        Schema::table('contractors', function (Blueprint $table) {
            $table->foreignId('governorate_id')->nullable()->after('city')
                  ->constrained('governorates')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->after('governorate_id')
                  ->constrained('cities')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contractors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('governorate_id');
            $table->dropConstrainedForeignId('city_id');
        });

        Schema::dropIfExists('cities');
        Schema::dropIfExists('governorates');
    }
};
