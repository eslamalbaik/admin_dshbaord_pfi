<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contractors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('license_number')->unique()->nullable();
            $table->string('trade')->nullable();                  // تخصص
            $table->string('classification')->nullable();         // تصنيف: أ، ب، ج، د
            $table->year('established_year')->nullable();
            $table->string('owner_name')->nullable();
            $table->string('email')->unique()->nullable();
            $table->string('phone')->nullable();
            $table->string('city')->nullable();
            $table->text('address')->nullable();
            $table->enum('status', ['active', 'pending', 'expired', 'suspended'])->default('pending');
            $table->string('cr_file')->nullable();               // السجل التجاري
            $table->string('id_file')->nullable();               // الهوية
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contractors');
    }
};
