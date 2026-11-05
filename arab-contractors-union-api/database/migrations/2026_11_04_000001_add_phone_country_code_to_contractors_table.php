<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // كل الأرقام الموجودة فلسطينية — 970 افتراضياً (راجع App\Support\PhoneCountries)
        Schema::table('contractors', function (Blueprint $table) {
            $table->string('phone_country_code', 5)->default('970')->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('contractors', function (Blueprint $table) {
            $table->dropColumn('phone_country_code');
        });
    }
};
