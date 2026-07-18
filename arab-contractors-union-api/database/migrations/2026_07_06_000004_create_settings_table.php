<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * إعدادات عامة قابلة للتعديل من لوحة التحكم (key/value):
 * رقم واتساب الدعم، بريد الدعم، هاتف التواصل ... إلخ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('group')->default('general');
            $table->timestamps();
        });

        // قيم افتراضية لبيانات التواصل (شاشة الدعم الفني)
        DB::table('settings')->insert([
            ['key' => 'support_whatsapp', 'value' => '', 'group' => 'contact', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'support_email',    'value' => '', 'group' => 'contact', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'support_phone',    'value' => '', 'group' => 'contact', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
