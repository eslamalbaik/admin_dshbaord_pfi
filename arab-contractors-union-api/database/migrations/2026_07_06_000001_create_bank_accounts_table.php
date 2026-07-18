<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الحسابات البنكية للاتحاد — تُعرض في شاشة الدفع بالتطبيق
 * (شعار البنك، اسم البنك، رقم الآيبان) ليقوم المقاول بالتحويل إليها.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('bank_name');                 // اسم البنك (عربي)
            $table->string('bank_name_en')->nullable();  // اسم البنك (إنجليزي)
            $table->string('logo_path')->nullable();     // شعار البنك
            $table->string('iban');                      // رقم الآيبان
            $table->string('account_number')->nullable();
            $table->string('account_holder')->nullable(); // اسم صاحب الحساب / الاتحاد
            $table->string('swift')->nullable();
            $table->string('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
