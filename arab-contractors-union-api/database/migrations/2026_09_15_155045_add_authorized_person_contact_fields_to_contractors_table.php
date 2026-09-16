<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * بيانات التواصل مع المفوّض بالتوقيع — كان الجدول يحمل اسمه وصفته فقط
 * (authorized_person / authorized_person_title) بلا رقم هوية أو وسيلة اتصال.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contractors', function (Blueprint $table) {
            $table->string('authorized_person_id_number', 50)->nullable()->after('authorized_person_title');
            $table->string('authorized_person_phone', 20)->nullable()->after('authorized_person_id_number');
            $table->string('authorized_person_whatsapp', 20)->nullable()->after('authorized_person_phone');
        });
    }

    public function down(): void
    {
        Schema::table('contractors', function (Blueprint $table) {
            $table->dropColumn([
                'authorized_person_id_number',
                'authorized_person_phone',
                'authorized_person_whatsapp',
            ]);
        });
    }
};
