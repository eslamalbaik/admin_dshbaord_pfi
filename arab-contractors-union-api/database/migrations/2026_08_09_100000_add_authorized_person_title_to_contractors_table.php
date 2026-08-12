<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** REQ-26: "صفته بالسجل التجاري" — حقل مطلوب صراحة بمتطلبات طلب تعديل البروفايل، لا عمود مطابق حالياً. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contractors', function (Blueprint $table) {
            $table->string('authorized_person_title')->nullable()->after('authorized_person');
        });
    }

    public function down(): void
    {
        Schema::table('contractors', function (Blueprint $table) {
            $table->dropColumn('authorized_person_title');
        });
    }
};
