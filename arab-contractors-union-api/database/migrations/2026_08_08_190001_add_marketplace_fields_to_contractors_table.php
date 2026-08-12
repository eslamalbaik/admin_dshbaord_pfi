<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** REQ-08: إقرار إخلاء المسؤولية (أول مرة فقط) + حظر خاص بسوق الآليات (منفصل عن status/is_frozen العامين). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contractors', function (Blueprint $table) {
            $table->timestamp('equipment_disclaimer_accepted_at')->nullable()->after('is_frozen');
            $table->timestamp('equipment_banned_at')->nullable()->after('equipment_disclaimer_accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('contractors', function (Blueprint $table) {
            $table->dropColumn(['equipment_disclaimer_accepted_at', 'equipment_banned_at']);
        });
    }
};
