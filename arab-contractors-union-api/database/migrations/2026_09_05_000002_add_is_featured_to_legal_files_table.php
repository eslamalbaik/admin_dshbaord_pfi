<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** المكتبة القانونية: تمييز الملفات "الأكثر طلباً" لعرضها بقسم منفصل أعلى الشاشة. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_files', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('legal_files', function (Blueprint $table) {
            $table->dropColumn('is_featured');
        });
    }
};
